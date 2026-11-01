<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Doctor;
use App\Http\Controllers\Network;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Patient;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HealthEKG Telehealth - Route Web
|--------------------------------------------------------------------------
| Semua route membutuhkan autentikasi dan middleware role yang sesuai.
| Hak akses diterapkan di backend (middleware + filter query), bukan hanya
| dengan menyembunyikan menu.
*/

Route::middleware('guest')->group(function () {
    Route::get('/', fn () => redirect()->route('login'));
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.attempt');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Wajib ganti password (kecuali route di bawah) bila kebijakan aktif.
    Route::middleware('password')->group(function () {
        Route::get('/ubah-password', [ProfileController::class, 'changePasswordForm'])->name('password.change');
        Route::post('/ubah-password', [ProfileController::class, 'changePassword'])->name('password.update');

        Route::get('/profil', [ProfileController::class, 'show'])->name('profile.show');
        Route::post('/profil', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('/pengaturan-akun', [ProfileController::class, 'show'])->name('account.settings');

        Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifikasi/{id}/baca', [NotificationController::class, 'read'])->name('notifications.read');
        Route::post('/notifikasi/tandai-semua', [NotificationController::class, 'readAll'])->name('notifications.readAll');

        /*
        |----------------------------------------------------------------------
        | ROLE 1 - ADMIN PUSKESMAS
        |----------------------------------------------------------------------
        */
        Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
            Route::get('/dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');

            Route::get('/patients', [Admin\PatientController::class, 'index'])->name('patients.index');
            Route::get('/patients/create', [Admin\PatientController::class, 'create'])->name('patients.create');
            Route::post('/patients', [Admin\PatientController::class, 'store'])->name('patients.store');
            Route::get('/patients/{patient}', [Admin\PatientController::class, 'show'])->name('patients.show');
            Route::get('/patients/{patient}/edit', [Admin\PatientController::class, 'edit'])->name('patients.edit');
            Route::put('/patients/{patient}', [Admin\PatientController::class, 'update'])->name('patients.update');
            Route::post('/patients/{patient}/toggle', [Admin\PatientController::class, 'toggle'])->name('patients.toggle');
            Route::get('/patients-export', [Admin\PatientController::class, 'export'])->name('patients.export');

            Route::get('/doctors', [Admin\DoctorController::class, 'index'])->name('doctors.index');
            Route::get('/doctors/create', [Admin\DoctorController::class, 'create'])->name('doctors.create');
            Route::post('/doctors', [Admin\DoctorController::class, 'store'])->name('doctors.store');
            Route::get('/doctors/{doctor}', [Admin\DoctorController::class, 'show'])->name('doctors.show');
            Route::get('/doctors/{doctor}/edit', [Admin\DoctorController::class, 'edit'])->name('doctors.edit');
            Route::put('/doctors/{doctor}', [Admin\DoctorController::class, 'update'])->name('doctors.update');
            Route::post('/doctors/{doctor}/toggle', [Admin\DoctorController::class, 'toggle'])->name('doctors.toggle');

            Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
            Route::post('/users', [Admin\UserController::class, 'store'])->name('users.store');
            Route::post('/users/{user}/toggle', [Admin\UserController::class, 'toggle'])->name('users.toggle');
            Route::post('/users/{user}/reset-password', [Admin\UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::post('/users/{user}/role', [Admin\UserController::class, 'updateRole'])->name('users.role');

            Route::get('/devices', [Admin\DeviceController::class, 'index'])->name('devices.index');
            Route::get('/devices/create', [Admin\DeviceController::class, 'create'])->name('devices.create');
            Route::post('/devices', [Admin\DeviceController::class, 'store'])->name('devices.store');
            Route::get('/devices/{device}', [Admin\DeviceController::class, 'show'])->name('devices.show');
            Route::get('/devices/{device}/edit', [Admin\DeviceController::class, 'edit'])->name('devices.edit');
            Route::put('/devices/{device}', [Admin\DeviceController::class, 'update'])->name('devices.update');
            Route::post('/devices/{device}/toggle', [Admin\DeviceController::class, 'toggle'])->name('devices.toggle');

            // Sesi pemeriksaan & grafik EKG adalah ranah Dokter.
            // Admin hanya melihat catatan pemeriksaan (read-only).
            Route::get('/notes', [Admin\NoteController::class, 'index'])->name('notes.index');
            Route::get('/notes/{note}', [Admin\NoteController::class, 'show'])->name('notes.show');

            Route::get('/reports', [Admin\ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/export', [Admin\ReportController::class, 'export'])->name('reports.export');
        });

        /*
        |----------------------------------------------------------------------
        | ROLE 2 - DOKTER
        |----------------------------------------------------------------------
        */
        Route::prefix('doctor')->name('doctor.')->middleware('role:dokter')->group(function () {
            Route::get('/dashboard', [Doctor\DashboardController::class, 'index'])->name('dashboard');

            Route::get('/patients', [Doctor\MyPatientController::class, 'index'])->name('patients.index');
            Route::get('/patients/{patient}', [Doctor\MyPatientController::class, 'show'])->name('patients.show');

            Route::get('/monitoring', [Doctor\MonitoringController::class, 'index'])->name('monitoring.index');
            Route::get('/monitoring/{session}', [Doctor\MonitoringController::class, 'show'])->name('monitoring.show');
            Route::get('/monitoring/{session}/data', [Doctor\MonitoringController::class, 'data'])->name('monitoring.data');
            Route::post('/monitoring/{session}/end', [Doctor\MonitoringController::class, 'end'])->name('monitoring.end');

            Route::get('/sessions', [Doctor\HistoryController::class, 'index'])->name('history.index');

            Route::get('/notes', [Doctor\NoteController::class, 'index'])->name('notes.index');
            Route::post('/notes', [Doctor\NoteController::class, 'store'])->name('notes.store');
            Route::get('/notes/{note}/edit', [Doctor\NoteController::class, 'edit'])->name('notes.edit');
            Route::put('/notes/{note}', [Doctor\NoteController::class, 'update'])->name('notes.update');
        });

        /*
        |----------------------------------------------------------------------
        | ROLE 3 - PASIEN
        |----------------------------------------------------------------------
        */
        Route::prefix('patient')->name('patient.')->middleware('role:pasien')->group(function () {
            Route::get('/dashboard', [Patient\DashboardController::class, 'index'])->name('dashboard');

            Route::get('/ecg', [Patient\EcgController::class, 'index'])->name('ecg.index');
            Route::get('/ecg/{session}', [Patient\EcgController::class, 'show'])->name('ecg.show');
            Route::get('/ecg/{session}/data', [Patient\EcgController::class, 'data'])->name('ecg.data');

            Route::get('/history', [Patient\HistoryController::class, 'index'])->name('history.index');

            Route::get('/notes', [Patient\NoteController::class, 'index'])->name('notes.index');

            Route::get('/device', [Patient\DeviceInfoController::class, 'show'])->name('device.show');
        });

        /*
        |----------------------------------------------------------------------
        | ROLE 4 - NETWORK ENGINEER
        |----------------------------------------------------------------------
        */
        Route::prefix('network')->name('network.')->middleware('role:network_engineer')->group(function () {
            Route::get('/dashboard', [Network\DashboardController::class, 'index'])->name('dashboard');

            Route::get('/devices', [Network\ConnectivityController::class, 'devices'])->name('devices');
            Route::get('/mqtt', [Network\ConnectivityController::class, 'mqtt'])->name('mqtt');

            Route::get('/qos', [Network\QosController::class, 'monitoring'])->name('qos');
            Route::get('/payload-analysis', [Network\QosController::class, 'payload'])->name('payload');
            Route::get('/reports', [Network\QosController::class, 'reports'])->name('reports');
            Route::get('/reports/export', [Network\QosController::class, 'export'])->name('reports.export');

            Route::get('/security', [Network\SecurityController::class, 'index'])->name('security');
            Route::get('/logs', [Network\SecurityController::class, 'logs'])->name('logs');
            Route::get('/logs/export', [Network\SecurityController::class, 'exportLogs'])->name('logs.export');

            Route::get('/server-health', [Network\HealthController::class, 'server'])->name('server-health');
            Route::get('/events', [Network\HealthController::class, 'events'])->name('events');
        });
    });
});
