<?php

namespace App\Http\Controllers;

use App\Models\ForumComment;
use App\Models\ForumCommentReaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ForumCommentReactionController extends Controller
{
    public const ALLOWED_EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '👏'];

    /**
     * Toggle reaksi emoji milik user saat ini pada sebuah komentar (klik lagi untuk batal).
     */
    public function toggle(Request $request, ForumComment $comment)
    {
        $this->authorize('update', $comment->meeting);

        $validated = $request->validate([
            'emoji' => ['required', Rule::in(self::ALLOWED_EMOJIS)],
        ]);

        $existing = ForumCommentReaction::where('forum_comment_id', $comment->id)
            ->where('user_id', Auth::id())
            ->where('emoji', $validated['emoji'])
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            ForumCommentReaction::create([
                'forum_comment_id' => $comment->id,
                'user_id' => Auth::id(),
                'emoji' => $validated['emoji'],
            ]);
        }

        return back();
    }
}
