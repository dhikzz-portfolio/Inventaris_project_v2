<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SetupCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * First probe only: confirm there is an “artisan setup” equivalent task
     * for this Laravel 12 project on Windows, then we can align the test with
     * the actual command or workflow.
     */
    public function test_project_setup_passes_on_windows_sqlite(): void
    {
        $this->markTestSkipped('No dedicated setup command found yet; keeping this test as a placeholder for the Windows SQLite setup probe.');
    }
}
