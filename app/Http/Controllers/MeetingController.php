<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Jobs\ProcessMeetingNotula;
use Illuminate\Support\Facades\Storage;
use App\Services\Meeting\EmailDraftGenerator;

class MeetingController extends Controller
{
    /**
     * Terapkan query filter unit.
     */
    public function getFilteredMeetingsQuery()
    {
        $user = Auth::user();
        $query = Meeting::query();

        // Jika bukan superadmin, filter berdasarkan unit
        if (!$user->hasRole('superadmin')) {
            $query->where('unit_id', $user->unit_id);
        }

        return $query;
    }

    /**
     * Menampilkan daftar rapat (sudah difilter).
     */
    public function index(Request $request)
    {
        $meetings = $this->getFilteredMeetingsQuery() // Gunakan query yang sudah difilter
            ->when($request->input('search'), function ($q, $search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('agenda', 'like', "%{$search}%")
                  ->orWhere('transcript', 'like', "%{$search}%")
                  ->orWhere('summary', 'like', "%{$search}%");
            })
            ->with('unit')
            ->orderBy('date', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Meetings/Index', [
            'meetings' => $meetings,
            'filters' => $request->only(['search'])
        ]);
    }

    /**
     * Menampilkan halaman 'Buat Rapat'.
     */
    public function create()
    {
        return Inertia::render('Meetings/Create');
    }

    /**
     * Menyimpan rapat baru.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'agenda' => 'nullable|string',
            'attendees' => 'nullable|string',
        ]);

        // Otomatis set unit_id dan user_id saat membuat
        Meeting::create([
            'title' => $validated['title'],
            'date' => $validated['date'],
            'agenda' => $validated['agenda'],
            'attendees' => $validated['attendees'],
            'status' => 'Dijadwalkan',
            'unit_id' => $user->unit_id, // WAJIB
            'user_id' => $user->id,
        ]);

        return redirect()->route('meetings.index')->with('success', 'Rapat berhasil dijadwalkan.');
    }

    /**
     * Menampilkan detail rapat (sudah difilter).
     */
    public function show(Meeting $meeting)
    {
        $this->authorize('view', $meeting);

        return Inertia::render('Meetings/Show', [
            'meeting' => $meeting->load([
                'actionItems',
                'chatMessages',
                'comments.user:id,name',
                'comments.attachments',
                'comments.reactions',
                'comments.mentionedUsers:id,name',
                'comments.replies.user:id,name',
                'comments.replies.attachments',
                'comments.replies.reactions',
                'comments.replies.mentionedUsers:id,name',
            ]),
            'unitUsers' => User::where('unit_id', $meeting->unit_id)->get(['id', 'name', 'email']),
            'emailPurposes' => EmailDraftGenerator::PURPOSES,
        ]);
    }

    /**
     * Menampilkan halaman 'Edit Rapat'.
     */
    public function edit(Meeting $meeting)
    {
        $this->authorize('update', $meeting);

         // Hanya boleh edit jika status masih Dijadwalkan
        if ($meeting->status !== 'Dijadwalkan') {
             return redirect()->route('meetings.show', $meeting->id)->with('error', 'Rapat yang sudah diproses tidak dapat diedit.');
        }

        return Inertia::render('Meetings/Edit', [
            'meeting' => $meeting
        ]);
    }

    /**
     * Update detail rapat.
     */
    public function update(Request $request, Meeting $meeting)
    {
        $this->authorize('update', $meeting);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'agenda' => 'nullable|string',
            'attendees' => 'nullable|string',
        ]);

        $meeting->update($validated);

        return redirect()->route('meetings.show', $meeting->id)->with('success', 'Detail rapat berhasil diperbarui.');
    }

    /**
     * Memulai proses notula AI (sudah difilter).
     */
    public function process(Request $request, Meeting $meeting)
    {
        $this->authorize('process', $meeting);

        $inputType = $request->input('type');
        $validated = [];
        $sourceFilePath = null;

        // Validasi berdasarkan tipe input
        if ($inputType === 'audio') {
            $validated = $request->validate(['audio_file' => 'required|file|mimetypes:audio/mpeg,audio/wav,audio/x-m4a,audio/mp4']);
        } elseif ($inputType === 'file') {
            $validated = $request->validate(['text_file' => 'required|file|mimetypes:text/plain,text/markdown']);
        } elseif ($inputType === 'text') {
            $validated = $request->validate(['text_input' => 'required|string']);
        } elseif ($inputType === 'image') {
            $validated = $request->validate(['image_file' => 'required|image|mimes:jpg,jpeg,png,webp|max:10240']);
        } else {
            return back()->withErrors(['type' => 'Tipe input tidak valid.']);
        }

        // =================================================================
        // PERBAIKAN LOGIKA PENYIMPANAN FILE (INI BAGIAN YANG BENAR)
        // =================================================================
        if ($inputType === 'audio') {
            $file = $validated['audio_file'];
            // Simpan di storage/app/public/audio_uploads
            // $sourceFilePath akan berisi "audio_uploads/filename.mp3"
            $sourceFilePath = $file->store('audio_uploads', 'public');
        } elseif ($inputType === 'file') {
            $file = $validated['text_file'];
            // Simpan di storage/app/public/text_uploads
            // $sourceFilePath akan berisi "text_uploads/filename.txt"
            $sourceFilePath = $file->store('text_uploads', 'public');
        } elseif ($inputType === 'text') {
            $fileName = 'manual_input_' . $meeting->id . '_' . time() . '.txt';
            // $path akan berisi "text_uploads/filename.txt"
            $path = 'text_uploads/' . $fileName;
            // Simpan di storage/app/public/text_uploads/filename.txt
            Storage::disk('public')->put($path, $validated['text_input']);
            $sourceFilePath = $path;
        } elseif ($inputType === 'image') {
            $file = $validated['image_file'];
            // Simpan di storage/app/public/image_uploads
            $sourceFilePath = $file->store('image_uploads', 'public');
        }

        // Update meeting dengan path file baru dan ubah status
        $meeting->update([
            'source_file_path' => $sourceFilePath,
            'status' => 'Memproses',
        ]);

        // Panggil Job untuk diproses di latar belakang
        ProcessMeetingNotula::dispatch($meeting);

        return redirect()->route('meetings.show', $meeting->id)
            ->with('success', 'Notula sedang diproses. Halaman akan diperbarui setelah selesai.');
    }

    /**
     * Menghapus rapat.
     */
    public function destroy(Meeting $meeting)
    {
        $this->authorize('delete', $meeting);

        // Hapus file fisik jika ada
        if ($meeting->source_file_path) {
            Storage::delete($meeting->source_file_path);
        }

        $meeting->delete();

        return redirect()->route('meetings.index')->with('success', 'Rapat berhasil dihapus.');
    }
}

