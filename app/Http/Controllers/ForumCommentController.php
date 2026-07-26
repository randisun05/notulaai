<?php

namespace App\Http\Controllers;

use App\Models\ForumComment;
use App\Models\Meeting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ForumCommentController extends Controller
{
    public function store(Request $request, Meeting $meeting)
    {
        $this->authorize('view', $meeting);

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
            'parent_id' => 'nullable|integer|exists:forum_comments,id',
        ]);

        if (!empty($validated['parent_id'])) {
            $parent = ForumComment::findOrFail($validated['parent_id']);
            if ($parent->meeting_id !== $meeting->id) {
                abort(404);
            }
        }

        ForumComment::create([
            'meeting_id' => $meeting->id,
            'user_id' => Auth::id(),
            'parent_id' => $validated['parent_id'] ?? null,
            'body' => $validated['body'],
        ]);

        return back()->with('success', 'Komentar berhasil ditambahkan.');
    }

    public function update(Request $request, ForumComment $comment)
    {
        $this->authorize('update', $comment);

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $comment->update($validated);

        return back()->with('success', 'Komentar berhasil diperbarui.');
    }

    public function destroy(ForumComment $comment)
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return back()->with('success', 'Komentar berhasil dihapus.');
    }
}
