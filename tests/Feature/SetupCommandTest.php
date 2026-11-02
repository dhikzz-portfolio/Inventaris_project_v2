<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Memastikan workflow setup (composer run setup) tetap tersedia untuk
     * instalasi baru di Windows/SQLite tanpa menjalankan proses berat di test.
     */
    public function test_project_setup_script_provides_windows_sqlite_workflow(): void
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);
        $setup = $composer['scripts']['setup'] ?? [];

        $this->assertNotEmpty($setup, 'composer.json harus mendefinisikan script "setup".');
        $this->assertContains('@php artisan migrate --force', $setup);
        $this->assertContains('npm run build', $setup);
    }
}
