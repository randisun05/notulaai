<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ForumCommentController;
use App\Http\Controllers\ForumCommentReactionController;
use App\Http\Controllers\LiveRecordingController;
use App\Http\Controllers\MeetingChatController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\MeetingEmailController;
use App\Http\Controllers\MeetingMinutesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecordingUploadController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SpeechController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskDispositionController;
use App\Http\Controllers\TaskExportController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebhookController;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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
Route::post('/dashboard/insight', [DashboardController::class, 'insight'])
    ->middleware(['auth', 'verified', 'throttle:ai'])->name('dashboard.insight');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // API Tokens (self-service Sanctum personal access tokens)
    Route::post('/api-tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store');
    Route::delete('/api-tokens/{token}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');

    // Meeting Routes
    Route::get('/meetings', [MeetingController::class, 'index'])->name('meetings.index');
    Route::get('/meetings/create', [MeetingController::class, 'create'])->name('meetings.create'); // Rute untuk menampilkan form
    Route::post('/meetings', [MeetingController::class, 'store'])->name('meetings.store'); // Rute untuk menyimpan data
    Route::get('/meetings/{meeting}', [MeetingController::class, 'show'])->name('meetings.show');
    Route::get('/meetings/{meeting}/recording', [MeetingController::class, 'recording'])->name('meetings.recording');
    Route::post('/meetings/{meeting}/process', [MeetingController::class, 'process'])->middleware('throttle:ai')->name('meetings.process'); // Rute baru untuk memproses notula

    // Notulen resmi (format dinas)
    Route::get('/meetings/{meeting}/minutes', [MeetingMinutesController::class, 'edit'])->name('meetings.minutes.edit');
    Route::post('/meetings/{meeting}/minutes/generate', [MeetingMinutesController::class, 'generate'])->middleware('throttle:ai')->name('meetings.minutes.generate');
    Route::put('/meetings/{meeting}/minutes', [MeetingMinutesController::class, 'update'])->name('meetings.minutes.update');
    Route::post('/meetings/{meeting}/minutes/submit', [MeetingMinutesController::class, 'submit'])->name('meetings.minutes.submit');
    Route::post('/meetings/{meeting}/minutes/approve', [MeetingMinutesController::class, 'approve'])->name('meetings.minutes.approve');
    Route::post('/meetings/{meeting}/minutes/return', [MeetingMinutesController::class, 'returnForRevision'])->name('meetings.minutes.return');
    Route::get('/meetings/{meeting}/minutes/pdf', [MeetingMinutesController::class, 'pdf'])->name('meetings.minutes.pdf');
    Route::get('/meetings/{meeting}/minutes/docx', [MeetingMinutesController::class, 'docx'])->name('meetings.minutes.docx');

    // Rekaman live rapat tatap muka (transkrip berjalan).
    Route::post('/meetings/{meeting}/live/start', [LiveRecordingController::class, 'start'])->middleware('throttle:ai')->name('meetings.live.start');
    Route::post('/meetings/{meeting}/live/resume', [LiveRecordingController::class, 'resume'])->name('meetings.live.resume');
    Route::put('/meetings/{meeting}/live/audio', [LiveRecordingController::class, 'append'])->name('meetings.live.append');
    Route::post('/meetings/{meeting}/live/stop', [LiveRecordingController::class, 'stop'])->name('meetings.live.stop');
    Route::post('/meetings/{meeting}/live/markers', [LiveRecordingController::class, 'marker'])->name('meetings.live.markers');
    Route::put('/meetings/{meeting}/speakers', [LiveRecordingController::class, 'renameSpeaker'])->name('meetings.speakers.rename');

    // Upload rekaman bertahap (bisa dilanjutkan) — satu-satunya jalur upload audio/video.
    Route::post('/meetings/{meeting}/recording-uploads', [RecordingUploadController::class, 'store'])->name('meetings.recording-uploads.store');
    Route::put('/recording-uploads/{upload}', [RecordingUploadController::class, 'append'])->name('recording-uploads.append');
    Route::post('/recording-uploads/{upload}/complete', [RecordingUploadController::class, 'complete'])->middleware('throttle:ai')->name('recording-uploads.complete');
    Route::delete('/recording-uploads/{upload}', [RecordingUploadController::class, 'destroy'])->name('recording-uploads.destroy');

    // --- TAMBAHKAN DUA ROUTE INI ---
    Route::get('/meetings/{meeting}/edit', [MeetingController::class, 'edit'])->name('meetings.edit');
    Route::put('/meetings/{meeting}', [MeetingController::class, 'update'])->name('meetings.update');
    // Tambahkan route untuk update, destroy di sini nanti\
    Route::delete('/meetings/{meeting}', [MeetingController::class, 'destroy'])->name('meetings.destroy'); // <-- TAMBAHKAN INI

    Route::post('/meetings/{meeting}/action-items/{actionItem}/convert-to-task', [TaskController::class, 'storeFromActionItem'])->name('meetings.action-items.convert');
    Route::post('/meetings/{meeting}/action-items/regenerate', [MeetingController::class, 'regenerateActionItems'])->middleware('throttle:ai')->name('meetings.action-items.regenerate');

    // AI Email Generator
    Route::post('/meetings/{meeting}/emails/generate', [MeetingEmailController::class, 'generate'])->middleware('throttle:ai')->name('meetings.emails.generate');
    Route::post('/meetings/{meeting}/emails/send', [MeetingEmailController::class, 'send'])->middleware('throttle:ai')->name('meetings.emails.send');

    // AI Chat per Meeting
    Route::post('/meetings/{meeting}/chat', [MeetingChatController::class, 'store'])->middleware('throttle:ai')->name('meetings.chat.store');

    // Task Routes
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('/tasks/kanban', [TaskController::class, 'kanban'])->name('tasks.kanban');
    Route::get('/tasks/calendar', [TaskController::class, 'calendar'])->name('tasks.calendar');
    Route::get('/tasks/export/pdf', [TaskExportController::class, 'pdf'])->name('tasks.export.pdf');
    Route::get('/tasks/export/excel', [TaskExportController::class, 'excel'])->name('tasks.export.excel');
    Route::get('/tasks/create', [TaskController::class, 'create'])->name('tasks.create');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::get('/tasks/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.update-status');
    Route::post('/tasks/{task}/submit-for-review', [TaskController::class, 'submitForReview'])->name('tasks.submit-for-review');
    Route::post('/tasks/{task}/approve', [TaskController::class, 'approve'])->name('tasks.approve');
    Route::post('/tasks/{task}/reject', [TaskController::class, 'reject'])->name('tasks.reject');
    Route::patch('/tasks/{task}/sla', [TaskController::class, 'updateSla'])->name('tasks.update-sla');
    Route::post('/tasks/{task}/dispositions', [TaskDispositionController::class, 'store'])->name('tasks.dispositions.store');

    // Analytics Routes
    Route::get('/analytics/productivity', [AnalyticsController::class, 'productivity'])->name('analytics.productivity');
    Route::get('/analytics/heatmap', [AnalyticsController::class, 'heatmap'])->name('analytics.heatmap');
    Route::get('/analytics/overdue', [AnalyticsController::class, 'overdue'])->name('analytics.overdue');

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
            Route::put('units/{unit}', [UnitController::class, 'update'])->name('units.update');
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

            // AUDIT LOG
            Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

            // WEBHOOKS
            Route::get('webhooks', [WebhookController::class, 'index'])->name('webhooks.index');
            Route::post('webhooks', [WebhookController::class, 'store'])->name('webhooks.store');
            Route::patch('webhooks/{webhook}', [WebhookController::class, 'update'])->name('webhooks.update');
            Route::delete('webhooks/{webhook}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');

        });

});

Route::post('/stt/test', [SpeechController::class, 'transcribe'])->middleware(['auth', 'can:access-admin-panel', 'throttle:ai']);

require __DIR__.'/auth.php';
