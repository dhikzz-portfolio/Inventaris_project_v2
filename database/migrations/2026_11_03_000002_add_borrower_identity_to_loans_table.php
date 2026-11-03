<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revisi V5: pembuat transaksi dan peminjam sebenarnya adalah dua identitas
     * berbeda. `borrower_id` tetap menjadi akun peminjam (nullable bila pihak
     * yang meminjam tidak memiliki akun), sedangkan `requested_by` mencatat
     * pengguna yang membuat transaksi. Nama dan data identitas peminjam
     * disimpan sebagai snapshot agar dokumen lama tidak berubah ketika akun
     * diubah kemudian.
     */
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (! Schema::hasColumn('loans', 'borrower_mode')) {
                $table->string('borrower_mode', 10)->default('SELF')->after('borrower_id');
            }

            if (! Schema::hasColumn('loans', 'borrower_name')) {
                $table->string('borrower_name')->nullable()->after('borrower_mode');
            }

            if (! Schema::hasColumn('loans', 'borrower_category')) {
                $table->string('borrower_category', 20)->nullable()->after('borrower_name');
            }

            if (! Schema::hasColumn('loans', 'borrower_class')) {
                $table->string('borrower_class', 50)->nullable()->after('borrower_category');
            }

            if (! Schema::hasColumn('loans', 'borrower_work_unit')) {
                $table->string('borrower_work_unit', 100)->nullable()->after('borrower_class');
            }

            if (! Schema::hasColumn('loans', 'requested_by')) {
                $table->unsignedBigInteger('requested_by')->nullable()->after('borrower_id');
                $table->index('requested_by', 'loans_requested_by_index');
            }
        });

        if (DB::getDriverName() !== 'sqlite' && ! $this->hasForeignKey('loans', 'loans_requested_by_foreign')) {
            Schema::table('loans', function (Blueprint $table) {
                $table->foreign('requested_by', 'loans_requested_by_foreign')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }

        // Peminjaman atas nama orang lain tidak mewajibkan akun sistem.
        Schema::table('loans', function (Blueprint $table) {
            $table->unsignedBigInteger('borrower_id')->nullable()->change();
        });

        // Backfill data lama: pembuat = peminjam, snapshot identitas dari akun.
        DB::table('loans')->orderBy('id')->chunkById(200, function ($loans): void {
            foreach ($loans as $loan) {
                $user = $loan->borrower_id
                    ? DB::table('users')->where('id', $loan->borrower_id)->first()
                    : null;

                DB::table('loans')->where('id', $loan->id)->update([
                    'requested_by' => $loan->requested_by ?? $loan->borrower_id,
                    'borrower_mode' => $loan->borrower_mode ?: 'SELF',
                    'borrower_name' => $loan->borrower_name ?: ($user->name ?? null),
                    'borrower_category' => $loan->borrower_category ?: ($user->borrower_type ?? null),
                    'borrower_class' => $loan->borrower_class ?: ($user->class_name ?? null),
                    'borrower_work_unit' => $loan->borrower_work_unit ?: ($user->work_unit ?? null),
                ]);
            }
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite' && $this->hasForeignKey('loans', 'loans_requested_by_foreign')) {
            Schema::table('loans', function (Blueprint $table) {
                $table->dropForeign('loans_requested_by_foreign');
            });
        }

        Schema::table('loans', function (Blueprint $table) {
            if (Schema::hasColumn('loans', 'requested_by')) {
                $table->dropIndex('loans_requested_by_index');
                $table->dropColumn('requested_by');
            }

            foreach (['borrower_mode', 'borrower_name', 'borrower_category', 'borrower_class', 'borrower_work_unit'] as $column) {
                if (Schema::hasColumn('loans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function hasForeignKey(string $table, string $name): bool
    {
        return collect(Schema::getForeignKeys($table))->pluck('name')->contains($name);
    }
};
