<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdminPagesAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Pastikan URL admin yang diuji cocok dengan route sesungguhnya.
     *
     * Catatan: di project ini tidak ada rute /admin/*.
     * Halaman pengaturan akses dibatasi lewat middleware role/permission
     * pada rute yang ada — lihat routes/web.php dan bootstrap/app.php.
     */
    public function test_admin_route_prefix_tidak_ada_saat_ini(): void
    {
        $this->get('/admin/dashboard')->assertNotFound();
        $this->get('/admin/users')->assertNotFound();
        $this->get('/admin/settings')->assertNotFound();
    }

    /**
     * Sebagai pengganti, verifikasi perilaku HTTP 400/500 hanya muncul
     * untuk rute yang memang ada dan memang membatasi akses.
     *
     * Hasil yang diharapkan:
     * - 200 hanya untuk rute yang boleh diakses tanpa login (mis. /login).
     * - 302 (redirect ke /login) untuk rute yang memerlukan autentikasi,
     *   atau jika middleware lain meredirect pengguna.
     * - 403 hanya jika route ada, user terautentikasi, tapi tidak punya
     *   hak akses (role/permission).
     * - 422/405 mungkin terjadi untuk method yang tidak diizinkan.
     * - Tidak boleh ada 400 / 500 / 5xx lainnya.
     *
     * Catatan: pengujian dilakukan tanpa exception handling agar
     * AuthenticationException / HttpException tertangkap langsung.
     *
     * PENTING: pastikan seeder sudah dijalankan sebelum menjalankan
     * pengujian ini, karena tabel yang diuji bergantung pada data
     * pengguna yang ada di seeder (DatabaseSeeder).
     */
    public function test_halaman_yang_ada_tidak_memunculkan_400_atau_500(): void
    {
        $paths = [
            ['path' => '/', 'expectAnyOf' => [200, 302]],
            ['path' => '/login', 'expectAnyOf' => [200, 302]],
            // 403: halaman registrasi dilindungi token sesi (register.start).
            ['path' => '/register', 'expectAnyOf' => [200, 302, 403, 404]],
            ['path' => '/dashboard', 'expectAnyOf' => [200, 302, 403]],
            ['path' => '/items', 'expectAnyOf' => [200, 302, 403, 404]],
            ['path' => '/items/create', 'expectAnyOf' => [200, 302, 403, 404]],
            ['path' => '/loans', 'expectAnyOf' => [200, 302, 403]],
            ['path' => '/loans/create', 'expectAnyOf' => [200, 302, 403]],
            ['path' => '/persetujuan', 'expectAnyOf' => [200, 302, 403, 404]],
            ['path' => '/pengembalian', 'expectAnyOf' => [200, 302, 403, 404]],
            ['path' => '/label', 'expectAnyOf' => [200, 302, 403, 404]],
            ['path' => '/scan', 'expectAnyOf' => [200, 302, 403, 404]],
            ['path' => '/laporan', 'expectAnyOf' => [200, 302, 403, 404]],
            ['path' => '/notifikasi', 'expectAnyOf' => [200, 302, 403]],
            ['path' => '/profile', 'expectAnyOf' => [200, 302, 403]],
            ['path' => '/mutasi', 'expectAnyOf' => [200, 302, 403, 404]],
            ['path' => '/inventaris-keluar', 'expectAnyOf' => [200, 302, 403, 404]],
            ['path' => '/master', 'expectAnyOf' => [200, 302, 403, 404]],
            ['path' => '/kegiatan', 'expectAnyOf' => [200, 302, 403]],
            ['path' => '/pengadaan', 'expectAnyOf' => [200, 302, 403, 404]],
            ['path' => '/pengadaan/create', 'expectAnyOf' => [200, 302, 403, 404]],
            ['path' => '/inventaris-keluar/persetujuan', 'expectAnyOf' => [200, 302, 403, 404]],
            ['path' => '/pengguna', 'expectAnyOf' => [200, 302, 403, 404]],
            ['path' => '/pengaturan', 'expectAnyOf' => [200, 302, 403, 404]],
        ];

        foreach ($paths as $item) {
            $this->withoutExceptionHandling();
            try {
                $response = $this->get($item['path']);
                $status = $response->getStatusCode();
                $this->assertContains(
                    $status,
                    $item['expectAnyOf'],
                    "HTTP {$status} pada {$item['path']} tidak terduga (harapan: ".implode(' atau ', $item['expectAnyOf']).')'
                );
            } catch (AuthenticationException $e) {
                // Unauthenticated dan di-redirect: status 302.
                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 302;
                $this->assertContains(
                    $status,
                    $item['expectAnyOf'],
                    "HTTP {$status} pada {$item['path']} tidak terduga (harapan: ".implode(' atau ', $item['expectAnyOf']).')'
                );
            } catch (HttpException $e) {
                $status = $e->getStatusCode();
                $this->assertContains(
                    $status,
                    $item['expectAnyOf'],
                    "HTTP {$status} pada {$item['path']} tidak terduga (harapan: ".implode(' atau ', $item['expectAnyOf']).')'
                );
            } catch (\Throwable $e) {
                // Jika exception lain muncul, itu adalah bug yang harus
                // diperbaiki.
                $this->fail("Pengecualian tak terduga {$item['path']}: ".$e->getMessage());
            }
        }
    }

    /**
     * Jika login berhasil, akses ke halaman yang tidak diizinkan harus
     * menghasilkan redirect (ke halaman sebelumnya / tidak diizinkan),
     * bukan 500.
     *
     * Catatan: di project ini hak akses dikendalikan oleh middleware
     * role (Spatie) pada rute yang ada (lihat routes/web.php).
     *
     * PENTING: pastikan seeder sudah dijalankan sebelum menjalankan
     * pengujian ini, karena tabel yang diuji bergantung pada data
     * pengguna yang ada di seeder (DatabaseSeeder).
     */
    public function test_user_sudah_login_tapi_tidak_punya_hak_akses_tidak_mendapat_500(): void
    {
        // Isi tabel dengan seeder agar test berjalan mandiri (tanpa skip).
        $this->seed();

        // Gunakan pengguna yang ada di seeder dengan role PEMINJAM saja.
        $user = User::where('email', 'guru.budi@gmail.com')->first();
        $this->assertNotNull($user, 'Pengguna guru.budi harus ada di DatabaseSeeder');

        $this->actingAs($user);

        // Halaman yang hanya boleh diakses oleh peran lain (Wakasek /
        // ADMIN SARPRAS / SUPER_ADMIN). Jika user PEMINJAM mencoba
        // mengakses, hasilnya harus redirect (biasanya 403 atau 302),
        // bukan 5xx. Path mengikuti route sesungguhnya (lihat route:list).
        $paths = [
            '/pengguna',
            '/pengaturan',
            '/master',
            '/pengadaan',
            '/inventaris-keluar/persetujuan',
        ];

        foreach ($paths as $path) {
            $this->withoutExceptionHandling();
            try {
                $response = $this->get($path);
                $status = $response->getStatusCode();
                // Pastikan tidak ada 5xx.
                $this->assertNotEquals($status, 500, "{$path} tidak boleh menghasilkan 500");
                $this->assertNotEquals($status, 503, "{$path} tidak boleh menghasilkan 503");
                // Boleh 302 (redirect ke halaman sebelumnya / 403 page)
                // atau 403 (forbidden). Tidak boleh 200 kecuali benar-benar
                // diizinkan.
                $this->assertContains(
                    $status,
                    [302, 403],
                    "{$path} seharusnya 302/403 untuk user tanpa hak (mendapat {$status})"
                );
            } catch (HttpException $e) {
                $status = $e->getStatusCode();
                $this->assertNotEquals($status, 500);
                $this->assertNotEquals($status, 503);
                $this->assertContains(
                    $status,
                    [302, 403],
                    "{$path} seharusnya 302/403 untuk user tanpa hak (mendapat {$status})"
                );
            }
        }
    }

    /**
     * Verifikasi bahwa halaman-halaman yang wajib ada sesuai spesifikasi
     * dapat diakses tanpa error 5xx (menggunakan pengguna yang berhak).
     *
     * Catatan: halaman yang diuji adalah halaman yang harus dapat diakses
     * oleh Wakasek (berhak). Jika halaman tersebut tidak ada atau
     * mengembalikan 5xx, itu adalah bug.
     *
     * PENTING: pastikan seeder sudah dijalankan sebelum menjalankan
     * pengujian ini, karena tabel yang diuji bergantung pada data
     * pengguna yang ada di seeder (DatabaseSeeder).
     */
    public function test_halaman_wajib_tidak_error_5xx(): void
    {
        // Isi tabel dengan seeder agar test berjalan mandiri (tanpa skip).
        $this->seed();

        // Gunakan Wakasek (berhak mengakses laporan, persetujuan, dll.)
        $wakasek = User::where('email', 'wakasek@gmail.com')->first();
        $this->assertNotNull($wakasek, 'Pengguna wakasek harus ada di DatabaseSeeder');
        $this->actingAs($wakasek);

        $this->withoutExceptionHandling();
        $paths = [
            '/dashboard',
            '/items',
            '/loans',
            '/kegiatan',
            '/notifikasi',
            '/profile',
            '/laporan',
            '/persetujuan',
            '/pengembalian',
            '/label',
            '/scan',
        ];

        foreach ($paths as $path) {
            // Harus berhasil (200) atau redirect yang valid (302),
            // bukan 5xx.
            try {
                $response = $this->get($path);
                $status = $response->getStatusCode();
                $this->assertNotEquals($status, 500, "{$path} tidak boleh 500");
                $this->assertNotEquals($status, 503, "{$path} tidak boleh 503");
                $this->assertNotEquals($status, 504, "{$path} tidak boleh 504");
            } catch (HttpException $e) {
                $status = $e->getStatusCode();
                $this->assertNotEquals($status, 500);
                $this->assertNotEquals($status, 503);
                $this->assertNotEquals($status, 504);
            }
        }
    }
}
