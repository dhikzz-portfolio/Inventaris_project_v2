<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Support\Str;

class ItemTypeCodeService
{
    /**
     * Generate a stable 3-digit code for item type
     */
    public function generate(string $name): ?string
    {
        // Normalize name for deterministic generation
        $normalized = strtoupper(Str::ascii(trim($name)));
        $normalized = preg_replace('/[^A-Z0-9]/', '', $normalized);
        
        if (empty($normalized)) {
            return null;
        }
        
        // Try to generate deterministic code based on name
        $hash = crc32($normalized);
        $code = str_pad((string) (abs($hash) % 1000), 3, '0', STR_PAD_LEFT);
        
        // Check uniqueness
        if (Item::where('item_type_code', $code)->exists()) {
            // Try different variations
            for ($i = 1; $i < 1000; $i++) {
                $candidate = str_pad((string) ((abs($hash) + $i) % 1000), 3, '0', STR_PAD_LEFT);
                if (! Item::where('item_type_code', $candidate)->exists()) {
                    return $candidate;
                }
            }
            // If all codes taken (though unlikely with 1000 possibilities)
            throw new \Exception('Ruang kode jenis barang tiga digit telah habis.');
        }
        
        return $code;
    }
    
    public function ensureCode(Item $catalog): void
    {
        if (empty($catalog->item_type_code)) {
            $code = $this->generate($catalog->name);
            if ($code) {
                $catalog->item_type_code = $code;
                $catalog->save();
            }
        }
    }
}
