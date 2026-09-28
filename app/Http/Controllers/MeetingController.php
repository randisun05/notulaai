<?php

namespace App\Http\Controllers;

use App\Models\ForumComment;
use App\Models\ForumCommentAttachment;
use App\Models\Meeting;
use App\Models\MeetingMarker;
use App\Models\RecordingUpload;
use App\Models\User;
use App\Services\Meeting\ActivityLogger;
use App\Services\Meeting\EmailDraftGenerator;
use App\Services\Meeting\MeetingProcessingService;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MeetingController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * Terapkan query filter unit.
     */
    public function getFilteredMeetingsQuery()
    {
        $user = Auth::user();

        return Meeting::query()->visibleTo($user);
    }

    /**
     * Menampilkan daftar rapat (sudah difilter).
     */
    public function index(Request $request)
    {
        $meetings = $this->getFilteredMeetingsQuery() // Gunakan query yang sudah difilter
            ->when($request->input('search'), function ($q, $search) {
                // Dikelompokkan supaya OR tidak lolos dari filter unit visibleTo().
                $q->where(fn ($q) => $q->where('title', 'like', "%{$search}%")
                    ->orWhere('agenda', 'like', "%{$search}%")
                    ->orWhere('transcript', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%"));
            })
            ->with('unit')
            ->orderBy('date', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Meetings/Index', [
            'meetings' => $meetings,
            'filters' => $request->only(['search']),
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
        $meeting = Meeting::create([
            'title' => $validated['title'],
            'date' => $validated['date'],
            'agenda' => HtmlSanitizer::clean($validated['agenda'] ?? null),
            'attendees' => $validated['attendees'],
            'status' => 'Dijadwalkan',
            'unit_id' => $user->unit_id, // WAJIB
            'user_id' => $user->id,
        ]);

        $this->activityLogger->log($meeting, $user, 'meeting.created', "{$user->name} menjadwalkan rapat ini.");

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
                'activities.user:id,name',
            ]),
            'unitUsers' => User::where('unit_id', $meeting->unit_id)->get(['id', 'name', 'email']),
            'emailPurposes' => EmailDraftGenerator::PURPOSES,
            // Di-poll halaman (partial reload) selama status Memproses / Berlangsung.
            'progress' => fn () => $meeting->fresh()->processingProgress(),
            'live' => fn () => $meeting->fresh()->liveState(Auth::user()),
            'markerTypes' => MeetingMarker::TYPES,
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
            'meeting' => $meeting,
        ]);
    }

    /**
     * Update detail rapat.
     */
    public function update(Request $request, Meeting $meeting)
    {
        $this->authorize('update', $meeting);

        if ($meeting->status !== 'Dijadwalkan') {
            return redirect()->route('meetings.show', $meeting->id)->with('error', 'Rapat yang sudah diproses tidak dapat diedit.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'agenda' => 'nullable|string',
            'attendees' => 'nullable|string',
        ]);

        if (array_key_exists('agenda', $validated)) {
            $validated['agenda'] = HtmlSanitizer::clean($validated['agenda']);
        }

        $meeting->update($validated);

        return redirect()->route('meetings.show', $meeting->id)->with('success', 'Detail rapat berhasil diperbarui.');
    }

    /**
     * Memulai proses notula AI (sudah difilter).
     */
    public function process(Request $request, Meeting $meeting, MeetingProcessingService $processingService)
    {
        $this->authorize('process', $meeting);

        if (! MeetingProcessingService::canStart($meeting)) {
            return back()->with('error', 'Rapat ini sedang atau sudah diproses.');
        }

        $inputType = $request->input('type');
        $validated = [];
        $sourceFilePath = null;

        // Validasi berdasarkan tipe input. Rekaman audio/video tidak lewat sini,
        // tapi lewat upload bertahap (RecordingUploadController).
        if ($inputType === 'file') {
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
        if ($inputType === 'file') {
            $file = $validated['text_file'];
            // Simpan di storage/app/public/text_uploads
            // $sourceFilePath akan berisi "text_uploads/filename.txt"
            $sourceFilePath = $file->store('text_uploads', 'public');
        } elseif ($inputType === 'text') {
            $fileName = 'manual_input_'.$meeting->id.'_'.time().'.txt';
            // $path akan berisi "text_uploads/filename.txt"
            $path = 'text_uploads/'.$fileName;
            // Simpan di storage/app/public/text_uploads/filename.txt
            Storage::disk('public')->put($path, $validated['text_input']);
            $sourceFilePath = $path;
        } elseif ($inputType === 'image') {
            $file = $validated['image_file'];
            // Simpan di storage/app/public/image_uploads
            $sourceFilePath = $file->store('image_uploads', 'public');
        }

        $processingService->start($meeting, $sourceFilePath, Auth::user());

        return redirect()->route('meetings.show', $meeting->id)
            ->with('success', 'Notula sedang diproses. Halaman akan diperbarui setelah selesai.');
    }

    /**
     * Putar rekaman rapat. Rekaman baru ada di disk privat, jadi tidak bisa
     * diakses lewat /storage — hanya lewat sini, setelah cek hak akses.
     * BinaryFileResponse mendukung Range request, jadi pemutar bisa di-seek.
     */
    public function recording(Meeting $meeting)
    {
        $this->authorize('view', $meeting);

        $disk = Storage::disk($meeting->source_disk ?: 'public');
        $extension = strtolower(pathinfo((string) $meeting->source_file_path, PATHINFO_EXTENSION));

        abort_unless(
            $meeting->source_file_path
                && in_array($extension, MeetingProcessingService::AUDIO_EXTENSIONS, true)
                && $disk->exists($meeting->source_file_path),
            404,
        );

        return response()->file($disk->path($meeting->source_file_path));
    }

    /**
     * Generate ulang Action Items dari transkrip yang sudah ada (tanpa re-upload file).
     */
    public function regenerateActionItems(Meeting $meeting, MeetingProcessingService $processingService)
    {
        $this->authorize('update', $meeting);

        try {
            $count = $processingService->regenerateActionItems($meeting);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->activityLogger->log(
            $meeting,
            Auth::user(),
            'meeting.action_items_regenerated',
            Auth::user()->name." men-generate ulang Action Items ({$count} item)."
        );

        return back()->with('success', "Action items berhasil digenerate ulang ({$count} item).");
    }

    /**
     * Menghapus rapat.
     */
    public function destroy(Meeting $meeting)
    {
        $this->authorize('delete', $meeting);

        // Baris komentar forum & lampirannya ikut terhapus lewat cascade DB,
        // tapi file fisiknya (semua di disk public) harus dibersihkan manual.
        $paths = ForumCommentAttachment::query()
            ->whereIn('forum_comment_id', ForumComment::where('meeting_id', $meeting->id)->select('id'))
            ->pluck('file_path')
            ->all();

        $partialUploads = RecordingUpload::where('meeting_id', $meeting->id)->get()->map->partialPath()->all();

        $meeting->delete();

        Storage::disk('public')->delete($paths);
        Storage::disk('local')->delete($partialUploads);
        if ($meeting->source_file_path) {
            Storage::disk($meeting->source_disk ?: 'public')->delete($meeting->source_file_path);
        }
        // Rekaman privat, potongan audio & sisa upload bertahap (semua di disk local).
        Storage::disk('local')->deleteDirectory("recordings/{$meeting->id}");
        Storage::disk('local')->deleteDirectory("meeting_segments/{$meeting->id}");
        Storage::disk('local')->deleteDirectory("minutes/{$meeting->id}");

        return redirect()->route('meetings.index')->with('success', 'Rapat berhasil dihapus.');
    }
}
