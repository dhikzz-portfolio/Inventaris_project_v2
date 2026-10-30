<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ItemTypeCodeService
{
    public function generate(string $name): string
    {
        $normalized = Str::upper(Str::ascii(Str::squish($name)));
        $normalized = preg_replace('/[^A-Z0-9]/', '', $normalized) ?? '';

        if ($normalized === '') {
            throw ValidationException::withMessages([
                'name' => 'Nama jenis barang tidak menghasilkan identitas kode yang valid.',
            ]);
        }

        $digest = hash('sha256', $normalized, true);
        $start = unpack('N', substr($digest, 0, 4))[1] % 1000;
        $usedCodes = array_fill_keys(
            Item::query()->whereNotNull('item_type_code')->pluck('item_type_code')->all(),
            true
        );
        $candidate = $this->firstAvailableCode($start, $usedCodes);

        if ($candidate === null) {
            throw ValidationException::withMessages([
                'name' => 'Seluruh ruang kode jenis barang tiga digit telah digunakan.',
            ]);
        }

        return $candidate;
    }

    /** @param array<string, bool> $usedCodes */
    private function firstAvailableCode(int $start, array $usedCodes): ?string
    {
        for ($offset = 0; $offset < 1000; $offset++) {
            $candidate = str_pad((string) (($start + $offset) % 1000), 3, '0', STR_PAD_LEFT);

            if (! isset($usedCodes[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    public function ensureCode(Item $catalog): void
    {
        if (blank($catalog->item_type_code)) {
            $code = $this->generate($catalog->name);

            if ($catalog->exists) {
                $updated = DB::table($catalog->getTable())
                    ->where($catalog->getKeyName(), $catalog->getKey())
                    ->whereNull('item_type_code')
                    ->update([
                        'item_type_code' => $code,
                        'updated_at' => now(),
                    ]);

                if ($updated === 0) {
                    $catalog->refresh();

                    return;
                }

                $catalog->setAttribute('item_type_code', $code);
                $catalog->syncOriginalAttribute('item_type_code');

                return;
            }

            $catalog->forceFill(['item_type_code' => $code])->save();
        }
    }
}
