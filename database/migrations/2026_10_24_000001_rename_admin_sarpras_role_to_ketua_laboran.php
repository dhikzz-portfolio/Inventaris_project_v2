<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Role `ADMIN_SARPRAS` digantikan oleh `KETUA_LABORAN`.
     *
     * Data pengguna dan tabel Spatie ikut dimigrasikan agar tidak ada akun
     * yang kehilangan akses setelah aplikasi diperbarui. Tidak ada baris yang
     * dihapus selain role lama yang sudah tidak terpakai.
     */
    public function up(): void
    {
        DB::table('users')->where('role', 'ADMIN_SARPRAS')->update(['role' => 'KETUA_LABORAN']);

        $old = DB::table('roles')->where('name', 'ADMIN_SARPRAS')->first();

        if ($old === null) {
            return;
        }

        $new = DB::table('roles')->where('name', 'KETUA_LABORAN')->first();

        if ($new === null) {
            DB::table('roles')->where('id', $old->id)->update(['name' => 'KETUA_LABORAN']);

            return;
        }

        DB::table('model_has_roles')->where('role_id', $old->id)->update(['role_id' => $new->id]);
        DB::table('role_has_permissions')->where('role_id', $old->id)->update(['role_id' => $new->id]);
        DB::table('roles')->where('id', $old->id)->delete();
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'KETUA_LABORAN')->update(['role' => 'ADMIN_SARPRAS']);

        DB::table('roles')->where('name', 'KETUA_LABORAN')->update(['name' => 'ADMIN_SARPRAS']);
    }
};
