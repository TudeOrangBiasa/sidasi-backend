<?php

namespace App\Http\Controllers;

use App\Models\Scan;
use Illuminate\Http\Request;
use OpenFoodFacts\Laravel\Facades\OpenFoodFacts;

class ScanController extends Controller
{
    public function scan(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string',
        ]);
        $barcode = $request->barcode;

        try {
            $data = \Illuminate\Support\Facades\Cache::remember("openfoodfacts_{$barcode}", now()->addMonth(), function () use ($barcode) {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'User-Agent' => env('OFF_USER_AGENT') . ' (' . env('APP_CONTACT_EMAIL') . ')'
                ])->get(env('OFF_API_BASE_URL') . "/product/{$barcode}.json");

                if (!$response->successful() || $response->json('status') !== 1) {
                    return null;
                }

                return $response->json('product');
            });

            if (!$data) {
                return response()->json(['message' => 'Produk tidak ditemukan di database Open Food Facts atau API tidak merespons.'], 404);
            }

            $produsen = $data['brands'] ?? $data['creator'] ?? 'Tidak Diketahui';
            
            $jenis_plastik = current(explode(',', $data['packaging'] ?? 'Tidak Diketahui'));
            if (stripos($data['packaging'] ?? '', 'PET') !== false) {
                $jenis_plastik = 'Botol Plastik (PET)';
            }

            $scan = Scan::create([
                'user_id' => $request->user()->id,
                'barcode_number' => $barcode,
                'produsen' => $produsen,
                'jenis_plastik' => $jenis_plastik,
            ]);

            $user = $request->user();
            $user->total_points += 10;
            $user->save();

            return response()->json([
                'message' => 'Berhasil memindai produk.',
                'product' => [
                    'barcode' => $barcode,
                    'produsen' => $produsen,
                    'jenis_plastik' => $jenis_plastik,
                    'product_name' => $data['product_name'] ?? 'Produk Tanpa Nama',
                    'brand' => $data['brands'] ?? 'Brand Umum',
                    'image_url' => $data['image_url'] ?? null,
                    'is_verified' => isset($data['brands']),
                ],
                'points_earned' => 10,
                'total_points' => $user->total_points
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menghubungi API Open Food Facts.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
