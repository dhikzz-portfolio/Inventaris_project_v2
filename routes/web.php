<?php

use App\Http\Controllers\DashboardController;
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
use App\Http\Controllers\SecureFileController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WriteOffController;
use Illuminate\Support\Facades\Route;

$managers = 'SUPER_ADMIN|ADMIN_SARPRAS';
$approvers = 'SUPER_ADMIN|ADMIN_SARPRAS|LABORAN';

Route::get('/manifest.json', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/sw.js', [PwaController::class, 'serviceWorker'])->name('pwa.service-worker');

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () use ($managers, $approvers) {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // File internal (foto bukti, BA, TTD, BAST, laporan) — akses diperiksa per peran.
    Route::get('/files', SecureFileController::class)->name('files.show');

    // ================= KATALOG & BARANG (semua role melihat) =================
    Route::get('/items', [ItemController::class, 'index'])->name('items.index');
    Route::get('/items/{item}', [ItemController::class, 'show'])->name('items.show');
    Route::get('/items/{item}/label', [ItemController::class, 'printLabel'])->name('items.label');

    // ================= PEMINJAMAN (semua role dapat mengajukan) =================
    Route::get('/loans/create', [LoanController::class, 'create'])->name('loans.create');
    Route::post('/loans', [LoanController::class, 'store'])->name('loans.store');
    Route::get('/loans', [LoanController::class, 'index'])->name('loans.index');
    Route::get('/loans/{loan}', [LoanController::class, 'show'])->name('loans.show');
    Route::get('/loans/{loan}/bast', [LoanController::class, 'downloadBast'])->name('loans.bast');
    Route::post('/loans/{loan}/cancel', [LoanController::class, 'cancel'])->name('loans.cancel');

    // ================= EVENT / KEGIATAN =================
    Route::get('/kegiatan', [EventController::class, 'index'])->name('events.index');
    Route::get('/kegiatan/{event}', [EventController::class, 'show'])->name('events.show');

    // ================= NOTIFIKASI =================
    Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifikasi/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifikasi/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // ================= PROFIL =================
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // ============ LABORAN / ADMIN SARPRAS / WAKASEK ============
    Route::middleware('role:'.$approvers)->group(function () {
        // Persetujuan & Pengembalian
        Route::get('/persetujuan', [LoanController::class, 'pending'])->name('loans.pending');
        Route::post('/loans/{loan}/approve', [LoanController::class, 'approve'])->name('loans.approve');
        Route::post('/loans/{loan}/reject', [LoanController::class, 'reject'])->name('loans.reject');
        Route::post('/loans/{loan}/handover', [LoanController::class, 'handover'])->name('loans.handover');
        Route::get('/pengembalian', [LoanController::class, 'returns'])->name('loans.returns');
        Route::get('/pengembalian/{loan}', [LoanController::class, 'returnForm'])->name('loans.return-form');
        Route::post('/pengembalian/{loan}', [LoanController::class, 'processReturn'])->name('loans.return');

        // Label & Scan QR
        Route::get('/label', [LabelController::class, 'index'])->name('labels.index');
        Route::post('/label/cetak', [LabelController::class, 'print'])->name('labels.print');
        Route::get('/scan', [LabelController::class, 'scan'])->name('scan');
        Route::get('/scan/{internalId}', [LabelController::class, 'scanResolve'])->name('scan.resolve')->where('internalId', '.*');

    });

    // ============ ADMIN SARPRAS / WAKASEK ============
    Route::middleware('role:'.$managers)->group(function () {
        // Laporan (hanya pengelola — mencegah laboran mengekspor data seluruh sekolah)
        Route::get('/laporan', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/laporan/export/{type}/{period}', [ReportController::class, 'export'])->name('reports.export');
        Route::post('/laporan/generate', [ReportController::class, 'generate'])->name('reports.generate');

        // Pengadaan
        Route::get('/pengadaan/create', [ItemController::class, 'create'])->name('items.create');
        Route::post('/pengadaan', [ItemController::class, 'store'])->name('items.store');
        Route::get('/items/{item}/edit', [ItemController::class, 'edit'])->name('items.edit');
        Route::put('/items/{item}', [ItemController::class, 'update'])->name('items.update');

        // Mutasi lokasi
        Route::get('/mutasi', [MovementController::class, 'index'])->name('movements.index');
        Route::post('/mutasi', [MovementController::class, 'store'])->name('movements.store');

        // Inventaris keluar (penghapusan)
        Route::get('/inventaris-keluar', [WriteOffController::class, 'index'])->name('write-offs.index');
        Route::post('/inventaris-keluar', [WriteOffController::class, 'store'])->name('write-offs.store');

        // Kegiatan
        Route::post('/kegiatan', [EventController::class, 'store'])->name('events.store');
        Route::get('/kegiatan/{event}/bast', [EventController::class, 'downloadBast'])->name('events.bast');

        // Master data
        Route::get('/master', [MasterController::class, 'index'])->name('masters.index');
        Route::post('/master/majors', [MasterController::class, 'storeMajor'])->name('masters.store-major');
        Route::put('/master/majors/{major}', [MasterController::class, 'updateMajor'])->name('masters.update-major');
        Route::delete('/master/majors/{major}', [MasterController::class, 'destroyMajor'])->name('masters.destroy-major');
        Route::post('/master/rooms', [MasterController::class, 'storeRoom'])->name('masters.store-room');
        Route::put('/master/rooms/{room}', [MasterController::class, 'updateRoom'])->name('masters.update-room');
        Route::delete('/master/rooms/{room}', [MasterController::class, 'destroyRoom'])->name('masters.destroy-room');
        Route::post('/master/categories', [MasterController::class, 'storeCategory'])->name('masters.store-category');
        Route::put('/master/categories/{category}', [MasterController::class, 'updateCategory'])->name('masters.update-category');
        Route::delete('/master/categories/{category}', [MasterController::class, 'destroyCategory'])->name('masters.destroy-category');
    });

    // ============ WAKASEK SARPRAS / SUPER ADMIN ============
    Route::middleware('role:SUPER_ADMIN')->group(function () {
        Route::post('/loans/{loan}/close-shortage', [LoanController::class, 'closeShortage'])->name('loans.close-shortage');

        Route::get('/pengadaan', [ItemController::class, 'acquisitions'])->name('items.acquisitions');
        Route::post('/pengadaan/{item}/approve', [ItemController::class, 'approveAcquisition'])->name('items.approve');
        Route::post('/pengadaan/{item}/reject', [ItemController::class, 'rejectAcquisition'])->name('items.reject');

        Route::get('/inventaris-keluar/persetujuan', [WriteOffController::class, 'pending'])->name('write-offs.pending');
        Route::post('/inventaris-keluar/{writeOff}/approve', [WriteOffController::class, 'approve'])->name('write-offs.approve');
        Route::post('/inventaris-keluar/{writeOff}/reject', [WriteOffController::class, 'reject'])->name('write-offs.reject');

        Route::get('/pengguna', [UserController::class, 'index'])->name('users.index');
        Route::post('/pengguna', [UserController::class, 'store'])->name('users.store');
        Route::put('/pengguna/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/pengguna/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('/pengaturan', [SettingController::class, 'edit'])->name('settings.edit');
        Route::post('/pengaturan', [SettingController::class, 'update'])->name('settings.update');
    });
});

require __DIR__.'/auth.php';
