<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Services\Meeting\MeetingChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MeetingChatController extends Controller
{
    public function __construct(private readonly MeetingChatService $chat) {}

    public function store(Request $request, Meeting $meeting)
    {
        $this->authorize('view', $meeting);

        $validated = $request->validate([
            'question' => 'required|string|max:1000',
        ]);

        if (empty($meeting->transcript)) {
            return response()->json(['error' => 'Rapat ini belum memiliki transkrip untuk ditanyakan.'], 422);
        }

        try {
            $answer = $this->chat->ask($meeting, $validated['question'], Auth::user());
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Gagal mendapat jawaban dari AI: '.$e->getMessage()], 500);
        }

        return response()->json([
            'id' => $answer->id,
            'role' => $answer->role,
            'content' => $answer->content,
            'created_at' => $answer->created_at,
        ]);
    }
}
