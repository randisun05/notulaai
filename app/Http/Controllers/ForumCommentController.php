<?php

namespace App\Http\Controllers;

use App\Mail\CommentMentionNotification;
use App\Models\ForumComment;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Forum\MentionParser;
use App\Services\Meeting\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ForumCommentController extends Controller
{
    private const MAX_ATTACHMENTS = 5;

    public function __construct(
        private readonly MentionParser $mentionParser,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function store(Request $request, Meeting $meeting)
    {
        $this->authorize('view', $meeting);

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
            'parent_id' => 'nullable|integer|exists:forum_comments,id',
            'attachments' => 'nullable|array|max:'.self::MAX_ATTACHMENTS,
            'attachments.*' => 'file|max:5120|mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt',
        ]);

        $parentId = null;
        if (! empty($validated['parent_id'])) {
            $parent = ForumComment::findOrFail($validated['parent_id']);
            if ($parent->meeting_id !== $meeting->id) {
                abort(404);
            }
            // Forum hanya menampilkan satu tingkat balasan: balasan atas balasan
            // digantung ke komentar utamanya supaya tetap terlihat.
            $parentId = $parent->parent_id ?? $parent->id;
        }

        $comment = ForumComment::create([
            'meeting_id' => $meeting->id,
            'user_id' => Auth::id(),
            'parent_id' => $parentId,
            'body' => $validated['body'],
        ]);

        foreach ($request->file('attachments', []) as $file) {
            $comment->attachments()->create([
                'file_path' => $file->store('forum_attachments', 'public'),
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getClientMimeType(),
            ]);
        }

        $this->notifyMentions($comment, $meeting);

        $activityType = $comment->parent_id ? 'comment.replied' : 'comment.created';
        $activityDescription = $comment->parent_id
            ? Auth::user()->name.' membalas komentar di forum diskusi.'
            : Auth::user()->name.' menambahkan komentar di forum diskusi.';
        $this->activityLogger->log($meeting, Auth::user(), $activityType, $activityDescription);

        return back()->with('success', 'Komentar berhasil ditambahkan.');
    }

    public function update(Request $request, ForumComment $comment)
    {
        $this->authorize('update', $comment);

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $comment->update($validated);

        $this->notifyMentions($comment, $comment->meeting);

        return back()->with('success', 'Komentar berhasil diperbarui.');
    }

    public function destroy(ForumComment $comment)
    {
        $this->authorize('delete', $comment);

        // Balasan ikut terhapus lewat cascade DB, tapi file lampirannya harus
        // dibersihkan manual di sini sebelum baris komentarnya hilang.
        $comment->load('replies.attachments', 'attachments');

        foreach ($comment->replies as $reply) {
            foreach ($reply->attachments as $attachment) {
                Storage::disk('public')->delete($attachment->file_path);
            }
        }

        foreach ($comment->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $comment->delete();

        return back()->with('success', 'Komentar berhasil dihapus.');
    }

    /**
     * Deteksi "@Nama" di body komentar, simpan sebagai mention, dan kirim
     * email notifikasi ke user yang disebut (kecuali menyebut diri sendiri).
     */
    private function notifyMentions(ForumComment $comment, Meeting $meeting): void
    {
        $candidates = User::where('unit_id', $meeting->unit_id)
            ->where('id', '!=', $comment->user_id)
            ->get(['id', 'name', 'email']);

        $mentioned = $this->mentionParser->extract($comment->body, $candidates);

        $previouslyMentionedIds = $comment->mentionedUsers()->pluck('users.id')->all();
        $comment->mentionedUsers()->sync($mentioned->pluck('id'));

        // Hanya kirim email untuk mention yang baru muncul, supaya edit komentar
        // yang mention-nya tidak berubah tidak mengirim email berulang kali.
        $newlyMentioned = $mentioned->whereNotIn('id', $previouslyMentionedIds);

        foreach ($newlyMentioned as $user) {
            if ($user->email) {
                Mail::to($user->email)->send(new CommentMentionNotification($comment, $user));
            }
        }
    }
}
