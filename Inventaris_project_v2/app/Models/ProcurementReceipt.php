<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan barang yang benar-benar diterima untuk satu katalog pengadaan.
 *
 * Satu katalog dapat memiliki beberapa catatan penerimaan (mis. pengiriman
 * bertahap). Jumlah yang belum didistribusikan dihitung dari total penerimaan
 * dikurangi distribusi bersumber stok, sehingga barang yang sudah menjadi unit
 * inventaris tidak pernah dihitung dua kali.
 */
class ProcurementReceipt extends Model
{
    protected $fillable = [
        'item_catalog_id', 'quantity', 'condition', 'note', 'received_by', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_catalog_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
