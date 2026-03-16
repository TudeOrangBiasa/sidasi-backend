<?php

namespace App\Http\Controllers;

use App\Models\Scan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ScanController extends Controller
{
    public function scan(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string',
        ]);
        $barcode = $request->barcode;

        try {
            $data = Cache::remember("openfoodfacts_{$barcode}", now()->addMonth(), function () use ($barcode) {
                $response = Http::withHeaders([
                    'User-Agent' => env('OFF_USER_AGENT').' ('.env('APP_CONTACT_EMAIL').')',
                ])->get(env('OFF_API_BASE_URL')."/product/{$barcode}.json");

                if (! $response->successful() || $response->json('status') !== 1) {
                    return null;
                }

                return $response->json('product');
            });

            if (! $data) {
                return response()->json(['message' => 'Produk tidak ditemukan di database Open Food Facts atau API tidak merespons.'], 404);
            }

            $produsen = $data['brands'] ?? $data['creator'] ?? 'Tidak Diketahui';

            $product_name = trim($data['product_name'] ?? $data['product_name_en'] ?? '');
            if (empty($product_name) || strlen($product_name) < 2) {
                // If OFF returns empty string or garbage like "1"
                $product_name = 'Produk Tanpa Nama';
            }

            $brand = $data['brands'] ?? 'Brand Umum';
            $image_url = $data['image_front_url'] ?? $data['image_url'] ?? null;

            $quantity = trim($data['quantity'] ?? '');
            if (empty($quantity) && ! empty($data['product_quantity'])) {
                $quantity = trim($data['product_quantity'].' '.($data['product_quantity_unit'] ?? 'g/ml'));
            }
            if (empty($quantity)) {
                $quantity = null;
            }

            $nutriscore_grade = $data['nutriscore_grade'] ?? null;

            // Categories — use localized or fallback to raw
            $categories = null;
            if (! empty($data['categories'])) {
                // Take first 2 categories, clean up
                $cats = array_slice(explode(',', $data['categories']), 0, 2);
                $categories = implode(', ', array_map('trim', $cats));
            }

            // ===== FIXED: Material detection from packagings array =====
            // Old code used $data['packaging'] which is often "" (empty string)
            // New code uses $data['packagings'] (structured array) + packaging_materials_tags
            $materialMap = [
                'en:pet' => 'PET (Polyethylene Terephthalate)',
                'en:pet-1-polyethylene-terephthalate' => 'PET (Polyethylene Terephthalate)',
                'en:pp-5-polypropylene' => 'PP (Polypropylene)',
                'en:hdpe-2-high-density-polyethylene' => 'HDPE (High-Density Polyethylene)',
                'en:ldpe-4-low-density-polyethylene' => 'LDPE (Low-Density Polyethylene)',
                'en:ps-6-polystyrene' => 'PS (Polystyrene)',
                'en:plastic' => 'Plastik',
                'en:glass' => 'Kaca',
                'en:metal' => 'Logam',
                'en:paper' => 'Kertas',
                'en:cardboard' => 'Karton',
                'en:aluminium' => 'Aluminium',
                'en:tetra-pak' => 'Tetra Pak',
            ];

            $shapeMap = [
                'en:bottle' => 'Botol',
                'en:bottle-cap' => 'Tutup Botol',
                'en:can' => 'Kaleng',
                'en:box' => 'Kotak',
                'en:bag' => 'Kantong',
                'en:cup' => 'Gelas',
                'en:wrapper' => 'Bungkus',
                'en:tray' => 'Nampan',
                'en:jar' => 'Toples',
                'en:tube' => 'Tabung',
                'en:pouch' => 'Pouch',
            ];

            $jenis_plastik = 'Tidak Diketahui';
            $packaging_shape = null;

            // Priority 1: Structured packagings array (most reliable)
            if (! empty($data['packagings']) && is_array($data['packagings'])) {
                $mainPackaging = $data['packagings'][0];
                $materialTag = $mainPackaging['material'] ?? '';
                $shapeTag = $mainPackaging['shape'] ?? '';

                $materialLabel = $materialMap[$materialTag] ?? ucfirst(str_replace(['en:', '-'], ['', ' '], $materialTag));
                $shapeLabel = $shapeMap[$shapeTag] ?? ucfirst(str_replace(['en:', '-'], ['', ' '], $shapeTag));

                $combined = trim(trim($shapeLabel).' '.trim($materialLabel));
                if (! empty($combined)) {
                    $jenis_plastik = $combined;
                    $packaging_shape = $shapeLabel ?: null;
                }
            }

            // Priority 2: packaging_materials_tags
            if ($jenis_plastik === 'Tidak Diketahui' && ! empty($data['packaging_materials_tags']) && is_array($data['packaging_materials_tags'])) {
                $materialTag = $data['packaging_materials_tags'][0];
                $materialLabel = $materialMap[$materialTag] ?? ucfirst(str_replace(['en:', '-'], ['', ' '], $materialTag));

                if (! empty($data['packaging_shapes_tags']) && is_array($data['packaging_shapes_tags'])) {
                    $shapeTag = $data['packaging_shapes_tags'][0];
                    $shapeLabel = $shapeMap[$shapeTag] ?? ucfirst(str_replace(['en:', '-'], ['', ' '], $shapeTag));
                    $combined = trim(trim($shapeLabel).' '.trim($materialLabel));
                    if (! empty($combined)) {
                        $jenis_plastik = $combined;
                        $packaging_shape = $shapeLabel ?: null;
                    }
                } elseif (! empty(trim($materialLabel))) {
                    $jenis_plastik = trim($materialLabel);
                }
            }

            // Priority 3: Legacy packaging string field (fallback)
            if ($jenis_plastik === 'Tidak Diketahui' && ! empty($data['packaging'])) {
                $jenis_plastik = current(explode(',', $data['packaging']));
                if (stripos($data['packaging'], 'PET') !== false) {
                    $jenis_plastik = 'Botol PET (Polyethylene Terephthalate)';
                }
            }

            // Determine recyclability
            $is_recyclable = str_contains(strtolower($jenis_plastik), 'pet')
                || str_contains(strtolower($jenis_plastik), 'hdpe')
                || str_contains(strtolower($jenis_plastik), 'kaca')
                || str_contains(strtolower($jenis_plastik), 'aluminium')
                || str_contains(strtolower($jenis_plastik), 'logam')
                || str_contains(strtolower($jenis_plastik), 'kertas')
                || str_contains(strtolower($jenis_plastik), 'karton');

            // IP Geolocation via ip-api.com (free, no key needed)
            $ip = $request->ip();
            $location = ['city' => null, 'region' => null, 'country' => null, 'lat' => null, 'lon' => null];
            try {
                $geoResponse = Http::timeout(3)->get("http://ip-api.com/json/{$ip}?fields=status,city,regionName,country,lat,lon");
                if ($geoResponse->successful() && $geoResponse->json('status') === 'success') {
                    $geo = $geoResponse->json();
                    $location = [
                        'city' => $geo['city'] ?? null,
                        'region' => $geo['regionName'] ?? null,
                        'country' => $geo['country'] ?? null,
                        'lat' => $geo['lat'] ?? null,
                        'lon' => $geo['lon'] ?? null,
                    ];
                }
            } catch (\Exception $e) {
                // Geolocation failed silently — non-critical
            }

            $scan = Scan::create([
                'user_id' => $request->user()->id,
                'barcode_number' => $barcode,
                'product_name' => $product_name,
                'brand' => $brand,
                'produsen' => $produsen,
                'jenis_plastik' => $jenis_plastik,
                'image_url' => $image_url,
                'quantity' => $quantity,
                'categories' => $categories,
                'nutriscore_grade' => $nutriscore_grade,
                'packaging_shape' => $packaging_shape,
                'scan_ip' => $ip,
                'scan_city' => $location['city'],
                'scan_region' => $location['region'],
                'scan_country' => $location['country'],
                'scan_lat' => $location['lat'],
                'scan_lon' => $location['lon'],
            ]);

            $user = $request->user();
            $user->total_points += 10;
            $user->save();

            $locationLabel = collect([$location['city'], $location['region']])->filter()->implode(', ') ?: null;

            return response()->json([
                'message' => 'Berhasil memindai produk.',
                'product' => [
                    'id' => $scan->id,
                    'barcode' => $barcode,
                    'produsen' => $produsen,
                    'jenis_plastik' => $jenis_plastik,
                    'product_name' => $product_name,
                    'brand' => $brand,
                    'image_url' => $image_url,
                    'quantity' => $quantity,
                    'categories' => $categories,
                    'nutriscore_grade' => $nutriscore_grade,
                    'packaging_shape' => $packaging_shape,
                    'is_verified' => isset($data['brands']),
                    'is_recyclable' => $is_recyclable,
                    'location' => $locationLabel,
                    'scan_lat' => $location['lat'],
                    'scan_lon' => $location['lon'],
                ],
                'points_earned' => 10,
                'total_points' => $user->total_points,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menghubungi API Open Food Facts.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Scan $scan)
    {
        // Ensure user belongs to scan
        if ($scan->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $locationLabel = collect([$scan->scan_city, $scan->scan_region])->filter()->implode(', ') ?: null;

        $is_recyclable = str_contains(strtolower($scan->jenis_plastik ?? ''), 'pet')
            || str_contains(strtolower($scan->jenis_plastik ?? ''), 'hdpe')
            || str_contains(strtolower($scan->jenis_plastik ?? ''), 'kaca')
            || str_contains(strtolower($scan->jenis_plastik ?? ''), 'aluminium')
            || str_contains(strtolower($scan->jenis_plastik ?? ''), 'logam')
            || str_contains(strtolower($scan->jenis_plastik ?? ''), 'kertas')
            || str_contains(strtolower($scan->jenis_plastik ?? ''), 'karton');

        return response()->json([
            'product' => [
                'id' => $scan->id,
                'barcode' => $scan->barcode_number,
                'produsen' => $scan->produsen,
                'jenis_plastik' => $scan->jenis_plastik,
                'product_name' => $scan->product_name,
                'brand' => $scan->brand,
                'image_url' => $scan->image_url,
                'quantity' => $scan->quantity,
                'categories' => $scan->categories,
                'nutriscore_grade' => $scan->nutriscore_grade,
                'packaging_shape' => $scan->packaging_shape,
                'is_verified' => ! empty($scan->brand),
                'is_recyclable' => $is_recyclable,
                'location' => $locationLabel,
                'scan_lat' => $scan->scan_lat,
                'scan_lon' => $scan->scan_lon,
                'created_at' => $scan->created_at,
            ],
        ]);
    }
}
