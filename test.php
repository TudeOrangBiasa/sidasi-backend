<?php

$url = 'https://world.openfoodfacts.org/api/v2/product/8992761166007.json';
$opts = ['http' => ['header' => "User-Agent: SIDASIBali/1.0\r\n"]];
$ctx = stream_context_create($opts);
$json = file_get_contents($url, false, $ctx);
$data = json_decode($json, true)['product'] ?? [];
print_r([
    'product_name' => $data['product_name'] ?? null,
    'product_name_id' => $data['product_name_id'] ?? null,
    'product_name_en' => $data['product_name_en'] ?? null,
    'generic_name' => $data['generic_name'] ?? null,
    'quantity' => $data['quantity'] ?? null,
    'product_quantity' => $data['product_quantity'] ?? null,
    'product_quantity_unit' => $data['product_quantity_unit'] ?? null,
    'packaging' => $data['packaging'] ?? null,
    'packagings' => $data['packagings'] ?? [],
    'packaging_materials_tags' => $data['packaging_materials_tags'] ?? [],
]);
