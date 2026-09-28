<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingMinutes;
use App\Models\Setting;
use App\Models\User;
use App\Services\Meeting\MinutesService;
use App\Services\Meeting\MinutesWordExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use RuntimeException;

/**
 * Notulen resmi (format dinas) — lihat MinutesService.
 */
class MeetingMinutesController extends Controller
{
    private const MAX_PHOTOS = 12;

    public function __construct(private readonly MinutesService $service) {}

    public function edit(Meeting $meeting)
    {
        $this->authorize('view', $meeting);

        $minutes = $meeting->minutes?->load(['chairperson:id,name', 'minuteTaker:id,name', 'submitter:id,name', 'approver:id,name']);

        return Inertia::render('Meetings/Minutes', [
            'meeting' => $meeting->only(['id', 'title', 'date', 'status']),
            'minutes' => $minutes,
            'actionItems' => $meeting->actionItems()->get(['id', 'title', 'assignee_name', 'deadline']),
            'unitUsers' => User::where('unit_id', $meeting->unit_id)->orderBy('name')->get(['id', 'name']),
            'canEdit' => Auth::user()->can('update', $meeting) && (! $minutes || $minutes->isEditable()),
            'canApprove' => $minutes?->status === MeetingMinutes::STATUS_SUBMITTED && Auth::user()->can('approveMinutes', $meeting),
        ]);
    }

    public function generate(Meeting $meeting)
    {
        $this->authorize('update', $meeting);

        return $this->attempt(fn () => $this->service->draft($meeting, Auth::user()), $meeting, 'Draf notulen berhasil disusun. Periksa dan lengkapi sebelum diajukan.');
    }

    public function update(Request $request, Meeting $meeting)
    {
        $this->authorize('update', $meeting);
        $minutes = $meeting->minutes ?? abort(404);
        abort_unless($minutes->isEditable(), 409, 'Notulen yang sudah diajukan atau disahkan tidak bisa diubah.');

        $validated = $request->validate([
            'number' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:500',
            'location' => 'nullable|string|max:255',
            'time_range' => 'nullable|string|max:255',
            'chairperson_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('unit_id', $meeting->unit_id)],
            'chairperson_name' => 'nullable|string|max:255',
            'chairperson_title' => 'nullable|string|max:255',
            'minute_taker_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('unit_id', $meeting->unit_id)],
            'attendees' => 'nullable|string|max:10000',
            'agenda' => 'nullable|string|max:5000',
            'resume' => 'nullable|array|max:200',
            'resume.*.speaker' => 'nullable|string|max:255',
            'resume.*.text' => 'nullable|string|max:10000',
            'resume.*.response' => 'nullable|string|max:10000',
            'decisions' => 'nullable|array|max:50',
            'decisions.*' => 'nullable|string|max:2000',
            'closing' => 'nullable|string|max:5000',
        ]);

        $validated['resume'] = collect($validated['resume'] ?? [])
            ->map(fn ($item) => [
                'speaker' => trim($item['speaker'] ?? '') ?: null,
                'text' => trim($item['text'] ?? ''),
                'response' => trim($item['response'] ?? '') ?: null,
            ])
            ->filter(fn ($item) => $item['text'] !== '')
            ->values()->all();
        $validated['decisions'] = collect($validated['decisions'] ?? [])->map(fn ($d) => trim((string) $d))->filter()->values()->all();
        // Pimpinan dipilih dari pengguna → nama bebas dikosongkan (dan sebaliknya).
        if (! empty($validated['chairperson_id'])) {
            $validated['chairperson_name'] = null;
        }

        $minutes->update($validated);

        return back()->with('success', 'Notulen disimpan.');
    }

    /**
     * Foto/tangkapan layar untuk halaman DOKUMENTASI (disk privat `local`).
     */
    public function uploadPhotos(Request $request, Meeting $meeting)
    {
        $this->authorize('update', $meeting);
        $minutes = $meeting->minutes ?? abort(404);
        abort_unless($minutes->isEditable(), 409, 'Notulen yang sudah diajukan atau disahkan tidak bisa diubah.');

        $existing = $minutes->documentation ?? [];
        $request->validate([
            'photos' => 'required|array|min:1|max:'.max(1, self::MAX_PHOTOS - count($existing)),
            'photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:10240',
        ], ['photos.max' => 'Maksimal '.self::MAX_PHOTOS.' foto dokumentasi.']);

        foreach ($request->file('photos') as $photo) {
            $existing[] = $photo->store("minutes/{$meeting->id}", 'local');
        }
        $minutes->update(['documentation' => $existing]);

        return back()->with('success', 'Foto dokumentasi ditambahkan.');
    }

    public function photo(Meeting $meeting, int $index)
    {
        $this->authorize('view', $meeting);
        $path = $meeting->minutes?->documentation[$index] ?? abort(404);

        return response()->file(Storage::disk('local')->path($path));
    }

    public function deletePhoto(Meeting $meeting, int $index)
    {
        $this->authorize('update', $meeting);
        $minutes = $meeting->minutes ?? abort(404);
        abort_unless($minutes->isEditable(), 409, 'Notulen yang sudah diajukan atau disahkan tidak bisa diubah.');

        $photos = $minutes->documentation ?? [];
        $path = $photos[$index] ?? abort(404);
        Storage::disk('local')->delete($path);
        array_splice($photos, $index, 1);
        $minutes->update(['documentation' => $photos]);

        return back()->with('success', 'Foto dokumentasi dihapus.');
    }

    public function submit(Meeting $meeting)
    {
        $this->authorize('update', $meeting);

        return $this->attempt(fn () => $this->service->submit($meeting->minutes ?? abort(404), Auth::user()), $meeting, 'Notulen diajukan untuk disahkan.');
    }

    public function approve(Meeting $meeting)
    {
        $this->authorize('approveMinutes', $meeting);

        return $this->attempt(fn () => $this->service->approve($meeting->minutes, Auth::user()), $meeting, 'Notulen disahkan.');
    }

    public function returnForRevision(Request $request, Meeting $meeting)
    {
        $this->authorize('approveMinutes', $meeting);
        $validated = $request->validate(['note' => 'required|string|max:2000']);

        return $this->attempt(fn () => $this->service->return($meeting->minutes, Auth::user(), $validated['note']), $meeting, 'Notulen dikembalikan ke notulis.');
    }

    public function pdf(Meeting $meeting)
    {
        $this->authorize('view', $meeting);
        $minutes = $meeting->minutes ?? abort(404);

        return Pdf::loadView('exports.minutes-pdf', $this->documentData($meeting, $minutes))
            ->setPaper('a4')
            ->download($this->fileName($meeting, $minutes, 'pdf'));
    }

    public function docx(Meeting $meeting, MinutesWordExporter $exporter)
    {
        $this->authorize('view', $meeting);
        $minutes = $meeting->minutes ?? abort(404);

        $path = $exporter->export($this->documentData($meeting, $minutes));

        return response()->download($path, $this->fileName($meeting, $minutes, 'docx'))->deleteFileAfterSend();
    }

    /**
     * @return array<string, mixed>
     */
    private function documentData(Meeting $meeting, MeetingMinutes $minutes): array
    {
        $setting = Setting::current();
        $logo = $setting->company_logo_path ? storage_path('app/public/'.$setting->company_logo_path) : null;

        return [
            'meeting' => $meeting,
            'minutes' => $minutes->load(['chairperson', 'minuteTaker', 'approver']),
            'actionItems' => $meeting->actionItems()->get(),
            'setting' => $setting,
            'logoPath' => $logo && is_file($logo) ? $logo : null,
            'date' => Carbon::parse($meeting->date)->locale('id'),
            'isDraft' => $minutes->status !== MeetingMinutes::STATUS_APPROVED,
            'attendances' => $meeting->attendances()->get(),
            'photos' => collect($minutes->documentation ?? [])
                ->map(fn (string $path) => Storage::disk('local')->path($path))
                ->filter(fn (string $path) => is_file($path))
                ->values(),
        ];
    }

    private function fileName(Meeting $meeting, MeetingMinutes $minutes, string $extension): string
    {
        return 'Notulen - '.Str::limit(Str::slug($meeting->title, ' '), 60, '')
            .($minutes->status === MeetingMinutes::STATUS_APPROVED ? '' : ' (DRAF)').".{$extension}";
    }

    private function attempt(callable $action, Meeting $meeting, string $success)
    {
        try {
            $action();
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('meetings.minutes.edit', $meeting)->with('success', $success);
    }
}
