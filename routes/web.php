<?php

use App\Models\User;
use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\MeetingEmailController;
use App\Http\Controllers\MeetingChatController;
use App\Http\Controllers\ForumCommentController;
use App\Http\Controllers\ForumCommentReactionController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Meeting Routes
    Route::get('/meetings', [MeetingController::class, 'index'])->name('meetings.index');
    Route::get('/meetings/create', [MeetingController::class, 'create'])->name('meetings.create'); // Rute untuk menampilkan form
    Route::post('/meetings', [MeetingController::class, 'store'])->name('meetings.store'); // Rute untuk menyimpan data
    Route::get('/meetings/{meeting}', [MeetingController::class, 'show'])->name('meetings.show');
    Route::post('/meetings/{meeting}/process', [MeetingController::class, 'process'])->name('meetings.process'); // Rute baru untuk memproses notula

    // --- TAMBAHKAN DUA ROUTE INI ---
    Route::get('/meetings/{meeting}/edit', [MeetingController::class, 'edit'])->name('meetings.edit');
    Route::put('/meetings/{meeting}', [MeetingController::class, 'update'])->name('meetings.update');
    // Tambahkan route untuk update, destroy di sini nanti\
     Route::delete('/meetings/{meeting}', [MeetingController::class, 'destroy'])->name('meetings.destroy'); // <-- TAMBAHKAN INI

    Route::post('/meetings/{meeting}/action-items/{actionItem}/convert-to-task', [TaskController::class, 'storeFromActionItem'])->name('meetings.action-items.convert');

    // AI Email Generator
    Route::post('/meetings/{meeting}/emails/generate', [MeetingEmailController::class, 'generate'])->name('meetings.emails.generate');
    Route::post('/meetings/{meeting}/emails/send', [MeetingEmailController::class, 'send'])->name('meetings.emails.send');

    // AI Chat per Meeting
    Route::post('/meetings/{meeting}/chat', [MeetingChatController::class, 'store'])->name('meetings.chat.store');

    // Task Routes
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.update-status');

    // Forum Routes
    Route::post('/meetings/{meeting}/comments', [ForumCommentController::class, 'store'])->name('meetings.comments.store');
    Route::put('/comments/{comment}', [ForumCommentController::class, 'update'])->name('comments.update');
    Route::delete('/comments/{comment}', [ForumCommentController::class, 'destroy'])->name('comments.destroy');
    Route::post('/comments/{comment}/reactions/toggle', [ForumCommentReactionController::class, 'toggle'])->name('comments.reactions.toggle');


      // ===============================================
    //      RUTE PANEL ADMIN (TAMBAHKAN INI)
    // ===============================================
    Route::middleware(['can:access-admin-panel'])
         ->prefix('admin')
         ->name('admin.')
         ->group(function () {

        // Rute untuk CRUD Unit
        Route::get('units', [UnitController::class, 'index'])->name('units.index');
        Route::post('units', [UnitController::class, 'store'])->name('units.store');
        // ... (Rute unit lainnya) ...
        Route::delete('units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');

        // RUTE MANAJEMEN USER (TAMBAHKAN INI)
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        // RUTE PENGATURAN APLIKASI
        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

    });



});

   Route::post('/stt/test', [App\Http\Controllers\SpeechController::class, 'transcribe']);

require __DIR__.'/auth.php';

