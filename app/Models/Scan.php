<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Scan extends Model
{
    protected $fillable = [
        'user_id',
        'barcode_number',
        'product_name',
        'brand',
        'produsen',
        'jenis_plastik',
        'image_url',
        'quantity',
        'categories',
        'nutriscore_grade',
        'packaging_shape',
        'scan_ip',
        'scan_city',
        'scan_region',
        'scan_country',
        'scan_lat',
        'scan_lon',
    ];

    /**
     * Get the user that owns the scan.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
