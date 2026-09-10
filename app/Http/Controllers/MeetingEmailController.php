<?php

namespace App\Http\Controllers;

use App\Mail\AiGeneratedEmail;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Meeting\ActivityLogger;
use App\Services\Meeting\EmailDraftGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class MeetingEmailController extends Controller
{
    public function __construct(
        private readonly EmailDraftGenerator $generator,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function generate(Request $request, Meeting $meeting)
    {
        $this->authorize('update', $meeting);

        $validated = $request->validate([
            'purpose' => ['required', Rule::in(array_keys(EmailDraftGenerator::PURPOSES))],
        ]);

        try {
            $draft = $this->generator->generate($meeting, $validated['purpose'], Auth::user());
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Gagal membuat draft email: '.$e->getMessage()], 500);
        }

        return response()->json($draft);
    }

    public function send(Request $request, Meeting $meeting)
    {
        $this->authorize('update', $meeting);

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'recipient_ids' => 'required|array|min:1',
            'recipient_ids.*' => 'integer|exists:users,id',
        ]);

        $recipients = User::whereIn('id', $validated['recipient_ids'])
            ->where('unit_id', $meeting->unit_id)
            ->whereNotNull('email')
            ->get();

        foreach ($recipients as $recipient) {
            Mail::to($recipient->email)->send(new AiGeneratedEmail($validated['subject'], $validated['body']));
        }

        $this->activityLogger->log(
            $meeting,
            Auth::user(),
            'email.sent',
            Auth::user()->name." mengirim email \"{$validated['subject']}\" ke {$recipients->count()} penerima.",
        );

        return back()->with('success', "Email berhasil dikirim ke {$recipients->count()} penerima.");
    }
}
