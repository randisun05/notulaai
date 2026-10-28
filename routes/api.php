<?php

use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MeetingController;
use App\Http\Controllers\Api\V1\TaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Read-only REST API, authenticated with the personal access tokens users
| create on their Profile page (Sanctum, "Authorization: Bearer <token>").
| Prefixed /api by bootstrap/app.php and rate-limited by throttle:api.
| Unit scoping and Policies are the same as the web UI. See docs/api.md.
*/

Route::middleware('auth:sanctum')->prefix('v1')->name('api.v1.')->group(function () {
    Route::get('me', MeController::class)->name('me');

    Route::get('meetings', [MeetingController::class, 'index'])->name('meetings.index');
    Route::get('meetings/{meeting}', [MeetingController::class, 'show'])->name('meetings.show');

    Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
});

// Deprecated: the original stub, kept unchanged so existing integrations don't
// break. New clients should use /api/v1/me.
Route::middleware('auth:sanctum')->get('/user', fn (Request $request) => $request->user());
