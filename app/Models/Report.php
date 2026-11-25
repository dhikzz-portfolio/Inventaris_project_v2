<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Report extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'period', 'scope', 'scope_id', 'pdf_path', 'excel_path',
        'generated_by', 'generated_at',
    ];

    protected function casts(): array
    {
        return ['generated_at' => 'datetime'];
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Label cakupan sesuai parameter laporan, mis. "Ruangan: Laboratorium Pengelasan",
     * bukan "RUANGAN #94".
     */
    public function scopeLabel(): string
    {
        return self::scopeLabelFor($this->scope, $this->scope_id, $this->generator);
    }

    /** Label cakupan dari parameter laporan; dipakai arsip dan nama file ekspor. */
    public static function scopeLabelFor(string $scope, ?int $scopeId, ?User $generator = null): string
    {
        return match ($scope) {
            'KATEGORI' => 'Kategori: '.(Category::find($scopeId)?->name ?? '-'),
            'RUANGAN' => 'Ruangan: '.(Room::find($scopeId)?->name ?? '-'),
            'JURUSAN' => 'Jurusan: '.(Major::find($scopeId)?->name ?? '-'),
            'GEDUNG' => 'Gedung: '.(Building::find($scopeId)?->name ?? '-'),
            default => $generator !== null && ! $generator->isSuperAdmin() && ! $generator->isAdmin()
                ? 'Seluruh laboratorium'
                : 'Seluruh sekolah',
        };
    }

    /** Nama dasar file ekspor/arsip, mengikuti nama parameter cakupan. */
    public static function fileBaseNameFor(string $period, string $scope, ?int $scopeId, ?User $generator = null): string
    {
        return 'laporan-'.$period.'-'.Str::slug(self::scopeLabelFor($scope, $scopeId, $generator));
    }

    /** Nama dasar file arsip laporan ini. */
    public function fileBaseName(): string
    {
        return self::fileBaseNameFor($this->period, $this->scope, $this->scope_id, $this->generator);
    }
}
