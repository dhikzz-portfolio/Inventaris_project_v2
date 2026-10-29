<?php

use App\Http\Controllers\CatalogController;
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
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WriteOffController;
use Illuminate\Support\Facades\Route;

$managers = 'SUPER_ADMIN|KETUA_LABORAN';
$approvers = 'SUPER_ADMIN|KETUA_LABORAN|LABORAN';

Route::get('/manifest.json', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/sw.js', [PwaController::class, 'serviceWorker'])->name('pwa.service-worker');

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'profile.completed'])->group(function () use ($managers, $approvers) {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // File internal (foto bukti, BA, TTD, BAST, laporan) — akses diperiksa per peran.
    Route::get('/files', SecureFileController::class)->name('files.show');

    // ================= KATALOG & UNIT (semua role melihat, dibatasi scope) =================
    Route::get('/catalogs', [CatalogController::class, 'index'])->name('catalogs.index');
    Route::get('/catalogs/{catalog}', [CatalogController::class, 'show'])->name('catalogs.show');
    Route::get('/units/{unit}', [UnitController::class, 'show'])->name('units.show');
    Route::get('/units/{unit}/label', [UnitController::class, 'label'])->middleware('role:SUPER_ADMIN|KETUA_LABORAN|LABORAN')->name('units.label');

    // Scan universal: semua pengguna yang sudah login boleh memindai, tetapi
    // detail internal tetap dibatasi cakupan akses di backend.
    Route::get('/scan', [LabelController::class, 'scan'])->name('scan');
    Route::get('/scan/{internalId}', [LabelController::class, 'scanResolve'])->name('scan.resolve')->where('internalId', '.*');

    // Rute lama dipertahankan selama masa transisi.
    Route::get('/items', [ItemController::class, 'index'])->name('items.index');
    Route::get('/items/{item}', [ItemController::class, 'show'])->name('items.show');
    Route::get('/items/{item}/label', [ItemController::class, 'printLabel'])->name('items.label');

    // ================= PEMINJAMAN (semua role dapat mengajukan) =================
    Route::get('/loans/create', [LoanController::class, 'create'])->name('loans.create');
    Route::post('/loans', [LoanController::class, 'store'])->name('loans.store');
    Route::get('/loans', [LoanController::class, 'index'])->name('loans.index');
    Route::get('/loans/{loan}', [LoanController::class, 'show'])->name('loans.show');
    Route::get('/loans/{loan}/surat', [LoanController::class, 'previewLetter'])->name('loans.letter.preview');
    Route::get('/loans/{loan}/bast', [LoanController::class, 'downloadBast'])->name('loans.bast');
    Route::post('/loans/{loan}/cancel', [LoanController::class, 'cancel'])->name('loans.cancel');

    // ================= EVENT / KEGIATAN =================
    // Semua pengguna melihat rekap; guru & staf boleh mencatat kegiatannya sendiri.
    Route::get('/kegiatan', [EventController::class, 'index'])->name('events.index');
    Route::post('/kegiatan', [EventController::class, 'store'])->name('events.store');
    Route::patch('/kegiatan/{event}', [EventController::class, 'update'])->name('events.update');
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
        Route::get('/loans/{loan}/serah-terima', [LoanController::class, 'handoverForm'])->name('loans.handover-form');
        Route::get('/loans/{loan}/serah-terima/scan', [LoanController::class, 'scanHandoverUnit'])->name('loans.handover-scan');
        Route::post('/loans/{loan}/dokumen/dicetak', [LoanController::class, 'markDocumentPrinted'])->name('loans.document.printed');
        Route::post('/loans/{loan}/dokumen/tanda-tangan-fisik', [LoanController::class, 'markDocumentSignedPhysical'])->name('loans.document.signed-physical');
        Route::post('/loans/{loan}/dokumen/tanda-tangan-digital', [LoanController::class, 'markDocumentSignedDigital'])->name('loans.document.signed-digital');
        Route::get('/pengembalian', [LoanController::class, 'returns'])->name('loans.returns');
        Route::get('/pengembalian/{loan}', [LoanController::class, 'returnForm'])->name('loans.return-form');
        Route::post('/pengembalian/{loan}', [LoanController::class, 'processReturn'])->name('loans.return');
        Route::post('/pengembalian/{loan}/selesai', [LoanController::class, 'finishReturn'])->name('loans.return.finish');

        // Label & Scan QR
        Route::patch('/units/{unit}', [UnitController::class, 'update'])->name('units.update');

        Route::get('/label', [LabelController::class, 'index'])->name('labels.index');
        Route::post('/label/cetak', [LabelController::class, 'print'])->name('labels.print');

    });

    // ============ ADMIN SARPRAS / WAKASEK ============
    Route::middleware('role:'.$managers)->group(function () {
        // Laporan (hanya pengelola — mencegah laboran mengekspor data seluruh sekolah)
        Route::get('/laporan', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/laporan/export/{type}/{period}', [ReportController::class, 'export'])->name('reports.export');
        Route::post('/laporan/generate', [ReportController::class, 'generate'])->name('reports.generate');
        Route::delete('/laporan/{report}', [ReportController::class, 'destroy'])->name('reports.destroy');

        // Impor laporan = pemulihan data unit, bukan pengganti backup database.
        Route::get('/laporan/impor', [ReportController::class, 'importForm'])->name('reports.import.form');
        Route::post('/laporan/impor/pratinjau', [ReportController::class, 'importPreview'])->name('reports.import.preview');
        Route::post('/laporan/impor', [ReportController::class, 'importConfirm'])->name('reports.import.confirm');

        // Pengadaan
        Route::get('/pengadaan/create', [ItemController::class, 'create'])->name('items.create');
        Route::post('/pengadaan', [ItemController::class, 'store'])->name('items.store');
        Route::get('/items/{item}/edit', [ItemController::class, 'edit'])->name('items.edit');
        Route::put('/items/{item}', [ItemController::class, 'update'])->name('items.update');

        // Mutasi lokasi
        Route::get('/mutasi', [MovementController::class, 'index'])->name('movements.index');
        Route::post('/mutasi', [MovementController::class, 'store'])->name('movements.store');
        Route::post('/units/{unit}/move', [UnitController::class, 'move'])->name('units.move');

        // Inventaris keluar (penghapusan)
        Route::get('/inventaris-keluar', [WriteOffController::class, 'index'])->name('write-offs.index');
        Route::post('/inventaris-keluar', [WriteOffController::class, 'store'])->name('write-offs.store');

        // Kegiatan (dokumen gabungan tetap khusus pengelola)
        Route::get('/kegiatan/{event}/bast', [EventController::class, 'downloadBast'])->name('events.bast');

        // Penghapusan/arsip katalog (aman: katalog bertransaksi tidak dihapus destruktif).
        Route::delete('/catalogs/{catalog}', [CatalogController::class, 'destroy'])
            ->name('catalogs.destroy');

        // Master data
        Route::get('/master', [MasterController::class, 'index'])->name('masters.index');
        Route::post('/master/majors', [MasterController::class, 'storeMajor'])->name('masters.store-major');
        Route::put('/master/majors/{major}', [MasterController::class, 'updateMajor'])->name('masters.update-major');
        Route::delete('/master/majors/{major}', [MasterController::class, 'destroyMajor'])->name('masters.destroy-major');
        Route::post('/master/buildings', [MasterController::class, 'storeBuilding'])->name('masters.store-building');
        Route::put('/master/buildings/{building}', [MasterController::class, 'updateBuilding'])->name('masters.update-building');
        Route::delete('/master/buildings/{building}', [MasterController::class, 'destroyBuilding'])->name('masters.destroy-building');
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
