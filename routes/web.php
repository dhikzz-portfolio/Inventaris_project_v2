<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DamageReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DistributionController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SecureFileController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SpecificationController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WriteOffController;
use Illuminate\Support\Facades\Route;

$masterView = 'master_jurusan.view|master_gedung.view|master_ruangan.view|master_kategori.view';

Route::get('/manifest.json', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/sw.js', [PwaController::class, 'serviceWorker'])->name('pwa.service-worker');

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'profile.completed', 'password.changed'])->group(function () use ($masterView) {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // File internal (foto bukti, BA, TTD, BAST, laporan) — akses diperiksa per peran.
    Route::get('/files', SecureFileController::class)->name('files.show');

    // ================= NOTIFIKASI =================
    Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifikasi/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifikasi/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // ================= PROFIL =================
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Super Admin tidak memiliki akses operasional inventaris.
    Route::middleware('inventory.access')->group(function () use ($masterView) {

        // ================= KATALOG & UNIT (sesuai permission + cakupan data) =================
        Route::middleware('permission:catalog.view')->group(function () {
            Route::get('/catalogs', [CatalogController::class, 'index'])->name('catalogs.index');
            Route::get('/catalogs/{catalog}', [CatalogController::class, 'show'])->name('catalogs.show');
            Route::get('/spesifikasi/{specification}', [SpecificationController::class, 'show'])->name('specifications.show');
            Route::get('/units/{unit}', [UnitController::class, 'show'])->name('units.show');

            // Rute lama dipertahankan selama masa transisi.
            Route::get('/items', [ItemController::class, 'index'])->name('items.index');
            Route::get('/items/{item}', [ItemController::class, 'show'])->name('items.show');
        });

        Route::get('/units/{unit}/label', [UnitController::class, 'label'])
            ->middleware('permission:unit_labels.print')->name('units.label');
        Route::get('/items/{item}/label', [ItemController::class, 'printLabel'])
            ->middleware('permission:unit_labels.print')->name('items.label');

        // Scan universal: pengguna dengan izin melihat inventaris boleh memindai.
        Route::middleware('permission:inventory.view')->group(function () {
            Route::get('/scan', [LabelController::class, 'scan'])->name('scan');
            Route::get('/scan/{internalId}', [LabelController::class, 'scanResolve'])->name('scan.resolve')->where('internalId', '.*');
        });

        // ================= PEMINJAMAN =================
        Route::get('/loans/create', [LoanController::class, 'create'])->middleware('permission:loan.create')->name('loans.create');
        Route::post('/loans', [LoanController::class, 'store'])->middleware('permission:loan.create')->name('loans.store');
        Route::get('/loans', [LoanController::class, 'index'])->middleware('permission:loan.view')->name('loans.index');
        Route::get('/loans/{loan}', [LoanController::class, 'show'])->middleware('permission:loan.view')->name('loans.show');
        Route::get('/loans/{loan}/surat', [LoanController::class, 'previewLetter'])->middleware('permission:loan.view')->name('loans.letter.preview');
        Route::get('/loans/{loan}/bast', [LoanController::class, 'downloadBast'])->middleware('permission:loan.view')->name('loans.bast');
        Route::post('/loans/{loan}/cancel', [LoanController::class, 'cancel'])->middleware('permission:loan.create')->name('loans.cancel');

        // ================= EVENT / KEGIATAN =================
        Route::get('/kegiatan', [EventController::class, 'index'])->middleware('permission:event.view')->name('events.index');
        Route::post('/kegiatan', [EventController::class, 'store'])->middleware('permission:event.create')->name('events.store');
        Route::patch('/kegiatan/{event}', [EventController::class, 'update'])->middleware('permission:event.edit')->name('events.update');
        Route::delete('/kegiatan/{event}', [EventController::class, 'destroy'])->middleware('permission:event.delete')->name('events.destroy');
        Route::get('/kegiatan/{event}', [EventController::class, 'show'])->middleware('permission:event.view')->name('events.show');
        Route::get('/kegiatan/{event}/bast', [EventController::class, 'downloadBast'])->middleware('permission:event.view')->name('events.bast');

        // ================= PERSETUJUAN & PENGEMBALIAN =================
        Route::get('/persetujuan', [LoanController::class, 'pending'])->middleware('permission:loan.approve')->name('loans.pending');
        Route::post('/loans/{loan}/approve', [LoanController::class, 'approve'])->middleware('permission:loan.approve')->name('loans.approve');
        Route::post('/loans/{loan}/reject', [LoanController::class, 'reject'])->middleware('permission:loan.reject')->name('loans.reject');
        Route::post('/loans/{loan}/handover', [LoanController::class, 'handover'])->middleware('permission:loan.handover')->name('loans.handover');
        Route::get('/loans/{loan}/serah-terima', [LoanController::class, 'handoverForm'])->middleware('permission:loan.handover')->name('loans.handover-form');
        Route::get('/loans/{loan}/serah-terima/scan', [LoanController::class, 'scanHandoverUnit'])->middleware('permission:loan.handover')->name('loans.handover-scan');
        Route::post('/loans/{loan}/dokumen/dicetak', [LoanController::class, 'markDocumentPrinted'])->middleware('permission:loan.handover')->name('loans.document.printed');
        Route::post('/loans/{loan}/dokumen/tanda-tangan-fisik', [LoanController::class, 'markDocumentSignedPhysical'])->middleware('permission:loan.handover')->name('loans.document.signed-physical');
        Route::post('/loans/{loan}/dokumen/tanda-tangan-digital', [LoanController::class, 'markDocumentSignedDigital'])->middleware('permission:loan.handover')->name('loans.document.signed-digital');
        Route::get('/pengembalian', [LoanController::class, 'returns'])->middleware('permission:loan.return')->name('loans.returns');
        Route::get('/pengembalian/{loan}', [LoanController::class, 'returnForm'])->middleware('permission:loan.return')->name('loans.return-form');
        Route::post('/pengembalian/{loan}', [LoanController::class, 'processReturn'])->middleware('permission:loan.return')->name('loans.return');
        Route::post('/pengembalian/{loan}/selesai', [LoanController::class, 'finishReturn'])->middleware('permission:loan.return')->name('loans.return.finish');

        // Label & Scan QR
        Route::patch('/units/{unit}', [UnitController::class, 'update'])->middleware('permission:inventory.edit')->name('units.update');
        Route::get('/label', [LabelController::class, 'index'])->middleware('permission:unit_labels.view')->name('labels.index');
        Route::post('/label/cetak', [LabelController::class, 'print'])->middleware('permission:unit_labels.print')->name('labels.print');

        // ================= LAPORAN =================
        Route::get('/laporan', [ReportController::class, 'index'])->middleware('permission:report.view')->name('reports.index');
        Route::get('/laporan/export/{type}/{period}', [ReportController::class, 'export'])->middleware('permission:report.export')->name('reports.export');
        Route::get('/laporan/pratinjau-excel/{period}', [ReportController::class, 'previewExcel'])->middleware('permission:report.export')->name('reports.preview-excel');
        Route::post('/laporan/generate', [ReportController::class, 'generate'])->middleware('permission:report.generate')->name('reports.generate');
        Route::delete('/laporan/{report}', [ReportController::class, 'destroy'])->middleware('permission:report.delete')->name('reports.destroy');
        Route::get('/laporan/impor/contoh', [ReportController::class, 'importTemplate'])->middleware('permission:report.import')->name('reports.import.template');
        Route::get('/laporan/impor', [ReportController::class, 'importForm'])->middleware('permission:report.import')->name('reports.import.form');
        Route::post('/laporan/impor/pratinjau', [ReportController::class, 'importPreview'])->middleware('permission:report.import')->name('reports.import.preview');
        Route::post('/laporan/impor', [ReportController::class, 'importConfirm'])->middleware('permission:report.import')->name('reports.import.confirm');

        // ================= CATALOG EDIT / DISTRIBUSI / MUTASI / PENGHAPUSAN =================
        Route::get('/items/{item}/edit', [ItemController::class, 'edit'])->middleware('permission:catalog.edit')->name('items.edit');
        Route::put('/items/{item}', [ItemController::class, 'update'])->middleware('permission:catalog.edit')->name('items.update');
        Route::delete('/catalogs/{catalog}', [CatalogController::class, 'destroy'])->middleware('permission:catalog.delete')->name('catalogs.destroy');

        Route::get('/distribusi', [DistributionController::class, 'index'])->middleware('permission:distribution.view')->name('distributions.index');

        Route::get('/mutasi', [MovementController::class, 'index'])->middleware('permission:movement.view')->name('movements.index');
        Route::post('/mutasi', [MovementController::class, 'store'])->middleware('permission:movement.create')->name('movements.store');
        Route::post('/units/{unit}/move', [UnitController::class, 'move'])->middleware('permission:movement.create')->name('units.move');

        Route::get('/inventaris-keluar', [WriteOffController::class, 'index'])->middleware('permission:write_off.view')->name('write-offs.index');
        Route::post('/inventaris-keluar', [WriteOffController::class, 'store'])->middleware('permission:write_off.create')->name('write-offs.store');

        // Master data — hanya Admin (dan Super Admin bila baseline Administrasi
        // sengaja diperluas, tetapi Super Admin tetap diblokir middleware
        // inventory.access sesuai pemisahan tanggung jawab).
        Route::get('/master', [MasterController::class, 'index'])->middleware('permission:'.$masterView)->name('masters.index');

        Route::post('/master/majors', [MasterController::class, 'storeMajor'])->middleware('permission:master_jurusan.create')->name('masters.store-major');
        Route::put('/master/majors/{major}', [MasterController::class, 'updateMajor'])->middleware('permission:master_jurusan.edit')->name('masters.update-major');
        Route::delete('/master/majors/{major}', [MasterController::class, 'destroyMajor'])->middleware('permission:master_jurusan.delete')->name('masters.destroy-major');
        Route::post('/master/buildings', [MasterController::class, 'storeBuilding'])->middleware('permission:master_gedung.create')->name('masters.store-building');
        Route::put('/master/buildings/{building}', [MasterController::class, 'updateBuilding'])->middleware('permission:master_gedung.edit')->name('masters.update-building');
        Route::delete('/master/buildings/{building}', [MasterController::class, 'destroyBuilding'])->middleware('permission:master_gedung.delete')->name('masters.destroy-building');
        Route::post('/master/rooms', [MasterController::class, 'storeRoom'])->middleware('permission:master_ruangan.create')->name('masters.store-room');
        Route::put('/master/rooms/{room}', [MasterController::class, 'updateRoom'])->middleware('permission:master_ruangan.edit')->name('masters.update-room');
        Route::delete('/master/rooms/{room}', [MasterController::class, 'destroyRoom'])->middleware('permission:master_ruangan.delete')->name('masters.destroy-room');
        Route::post('/master/categories', [MasterController::class, 'storeCategory'])->middleware('permission:master_kategori.create')->name('masters.store-category');
        Route::put('/master/categories/{category}', [MasterController::class, 'updateCategory'])->middleware('permission:master_kategori.edit')->name('masters.update-category');
        Route::delete('/master/categories/{category}', [MasterController::class, 'destroyCategory'])->middleware('permission:master_kategori.delete')->name('masters.destroy-category');

        // ================= PENGAADAAN =================
        Route::get('/pengadaan', [ItemController::class, 'acquisitions'])->middleware('permission:procurement.view')->name('items.acquisitions');
        Route::get('/pengadaan/create', [ItemController::class, 'create'])->middleware('permission:procurement.create')->name('items.create');
        Route::post('/pengadaan', [ItemController::class, 'store'])->middleware('permission:procurement.create')->name('items.store');
        Route::post('/pengadaan/{item}/ajukan', [ItemController::class, 'submitAcquisition'])->middleware('permission:procurement.create')->name('items.acquisitions.submit');
        Route::post('/pengadaan/{item}/konfirmasi', [ItemController::class, 'confirmAcquisition'])->middleware('permission:procurement.validate')->name('items.acquisitions.confirm');
        Route::post('/pengadaan/{item}/setujui', [ItemController::class, 'approveAcquisition'])->middleware('permission:procurement.approve')->name('items.approve');
        Route::post('/pengadaan/{item}/tolak', [ItemController::class, 'rejectAcquisition'])->middleware('permission:procurement.reject')->name('items.reject');
        Route::post('/pengadaan/{item}/proses', [ItemController::class, 'startAcquisition'])->middleware('permission:procurement.edit')->name('items.acquisitions.start');
        Route::get('/pengadaan/{item}/terima', [ItemController::class, 'receiveForm'])->middleware('permission:procurement.receive')->name('items.acquisitions.receive-form');
        Route::post('/pengadaan/{item}/terima', [ItemController::class, 'receiveAcquisition'])->middleware('permission:procurement.receive')->name('items.acquisitions.receive');
        Route::post('/pengadaan/{item}/catat', [ItemController::class, 'recordInventory'])->middleware('permission:procurement.receive')->name('items.acquisitions.record');

        // ================= LAPORAN KERUSAKAN =================
        Route::get('/kerusakan', [DamageReportController::class, 'index'])->middleware('permission:damage_report.view')->name('damage-reports.index');
        Route::get('/kerusakan/create', [DamageReportController::class, 'create'])->middleware('permission:damage_report.create')->name('damage-reports.create');
        Route::post('/kerusakan', [DamageReportController::class, 'store'])->middleware('permission:damage_report.create')->name('damage-reports.store');
        Route::get('/kerusakan/{damageReport}', [DamageReportController::class, 'show'])->middleware('permission:damage_report.view')->name('damage-reports.show');
        Route::patch('/kerusakan/{damageReport}', [DamageReportController::class, 'update'])->middleware('permission:damage_report.edit')->name('damage-reports.update');
    }); // akhir grup akses operasional inventaris

    // ============ PERSETUJUAN KHUSUS ADMIN (level 2) ============
    // Batasan identitas role tetap berlaku: hanya Admin yang boleh memutuskan
    // level akhir, terlepas dari konfigurasi permission.
    Route::middleware(['role:ADMIN', 'permission:loan.approve'])->group(function () {
        Route::post('/loans/{loan}/close-shortage', [LoanController::class, 'closeShortage'])->name('loans.close-shortage');
    });

    Route::middleware(['role:ADMIN', 'permission:write_off.view'])->group(function () {
        Route::get('/inventaris-keluar/persetujuan', [WriteOffController::class, 'pending'])->name('write-offs.pending');
    });

    Route::middleware(['role:ADMIN'])->group(function () {
        Route::post('/inventaris-keluar/{writeOff}/approve', [WriteOffController::class, 'approve'])->middleware('permission:write_off.approve')->name('write-offs.approve');
        Route::post('/inventaris-keluar/{writeOff}/reject', [WriteOffController::class, 'reject'])->middleware('permission:write_off.reject')->name('write-offs.reject');
    });

    // ============ ADMINISTRASI SISTEM (HANYA SUPER ADMIN) ============
    Route::middleware(['role:SUPER_ADMIN', 'permission:school_settings.view'])->group(function () {
        Route::get('/pengaturan', [SettingController::class, 'edit'])->name('settings.edit');
    });

    Route::middleware(['role:SUPER_ADMIN'])->group(function () {
        Route::post('/pengaturan', [SettingController::class, 'update'])->middleware('permission:school_settings.edit')->name('settings.update');

        Route::middleware('permission:roles.view')->group(function () {
            Route::get('/role-permission', [RoleController::class, 'index'])->name('roles.index');
            Route::get('/role-permission/{role}', [RoleController::class, 'show'])->name('roles.show');
        });

        Route::put('/role-permission/{role}', [RoleController::class, 'update'])
            ->middleware('permission:roles.manage')->name('roles.update');
        Route::post('/role-permission/{role}/reset', [RoleController::class, 'reset'])
            ->middleware('permission:roles.manage')->name('roles.reset');

        Route::middleware('permission:users.view')->group(function () {
            Route::get('/pengguna', [UserController::class, 'index'])->name('users.index');
            Route::get('/pengguna/{user}', [UserController::class, 'show'])->name('users.show');
        });

        Route::post('/pengguna', [UserController::class, 'store'])->middleware('permission:users.create')->name('users.store');
        Route::put('/pengguna/{user}', [UserController::class, 'update'])->middleware('permission:users.edit')->name('users.update');
        Route::post('/pengguna/{user}/status', [UserController::class, 'toggleStatus'])->middleware('permission:users.edit')->name('users.status');
        Route::delete('/pengguna/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('users.destroy');
    });
});

require __DIR__.'/auth.php';
