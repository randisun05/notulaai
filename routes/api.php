<?php

use App\Http\Controllers\Api\V1\MeetingController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| REST API untuk integrasi (dokumentasi: docs/API.md). Autentikasi pakai
| personal access token Sanctum yang dibuat user di halaman Profil; semua
| data dibatasi unit user yang sama seperti di aplikasi web.
*/

// Dipertahankan untuk integrasi lama; versi baru: /api/v1/user.
Route::middleware('auth:sanctum')->get('/user', fn (Request $request) => $request->user());

Route::middleware('auth:sanctum')->prefix('v1')->name('api.v1.')->group(function () {
    Route::get('user', fn (Request $request) => new UserResource($request->user()->load('unit')))->name('user');

    Route::get('meetings', [MeetingController::class, 'index'])->name('meetings.index');
    Route::post('meetings', [MeetingController::class, 'store'])->name('meetings.store');
    Route::get('meetings/{meeting}', [MeetingController::class, 'show'])->name('meetings.show');
    Route::post('meetings/{meeting}/transcript', [MeetingController::class, 'submitTranscript'])
        ->middleware('throttle:ai')->name('meetings.transcript');

    Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.update-status');
});
