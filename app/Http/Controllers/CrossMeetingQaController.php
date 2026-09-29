<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Services\Meeting\CrossMeetingQaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Throwable;

/**
 * Tanya Lintas Rapat — lihat CrossMeetingQaService.
 */
class CrossMeetingQaController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        return Inertia::render('Ask/Index', [
            'units' => $user->seesAllUnits() ? Unit::orderBy('name')->get(['id', 'name']) : [],
        ]);
    }

    public function ask(Request $request, CrossMeetingQaService $qa): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'required|string|max:1000',
            'unit_id' => 'nullable|integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        try {
            $result = $qa->ask(Auth::user(), $validated['question'], $validated);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Gagal mendapat jawaban dari AI: '.$e->getMessage()], 500);
        }

        return response()->json($result);
    }
}
