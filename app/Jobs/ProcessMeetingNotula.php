<?php

namespace App\Jobs;

use App\Models\Meeting;
use App\Models\User; // <-- Import User
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail; // <-- Import Mail
use App\Mail\MeetingSummary; // <-- Import Mailable
use OpenAI;

class ProcessMeetingNotula implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $meeting;
    public $timeout = 300; // 5 menit timeout

    // Model untuk peringkasan (chat)
    const CHAT_MODEL = 'openai/gpt-oss-20b:free';
    // Alamat server Python STT lokal Anda
    const PYTHON_STT_SERVICE_URL = 'http://127.0.0.1:5055/transcribe';

    public function __construct(Meeting $meeting)
    {
        $this->meeting = $meeting;
    }

    public function handle(): void
    {
        Log::info("Memulai pemrosesan untuk Rapat ID: {$this->meeting->id}");

        try {
            $filePath = $this->meeting->source_file_path;
            if (!$filePath || !Storage::disk('public')->exists($filePath)) {
                throw new \Exception("File sumber tidak ditemukan di path: {$filePath}");
            }
            $fullPath = Storage::disk('public')->path($filePath);
            $fileName = basename($filePath);

            $transcript = '';
            $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            // =================================================================
            // LANGKAH 1: TRANSKRIPSI (SPEECH-TO-TEXT)
            // =================================================================
            if (in_array($fileExtension, ['mp3', 'wav', 'm4a'])) {
                Log::info("Mengirim file audio ke server Python STT di " . self::PYTHON_STT_SERVICE_URL);

                $response = Http::timeout(300) // 5 menit timeout untuk STT
                    ->attach(
                        'file',
                        file_get_contents($fullPath),
                        $fileName
                    )->post(self::PYTHON_STT_SERVICE_URL);

                if (!$response->successful()) {
                    throw new \Exception("Server Python STT gagal: " . $response->body());
                }

                $transcript = $response->json('text');
                if (empty($transcript)) {
                    throw new \Exception("Transkrip dari server Python kosong.");
                }
                Log::info("Transkrip audio berhasil dibuat oleh server Python.");

            } elseif (in_array($fileExtension, ['txt', 'md'])) {
                Log::info("Membaca konten dari file teks.");
                $transcript = Storage::disk('public')->get($filePath);
            } else {
                throw new \Exception("Tipe file tidak didukung: {$fileExtension}");
            }

            if (empty(trim($transcript))) {
                throw new \Exception("Transkrip kosong, tidak bisa membuat rangkuman.");
            }
            Log::info("Transkrip berhasil dibuat.");

            // =================================================================
            // LANGKAH 2: PERINGKASAN (SUMMARIZATION) via OpenRouter
            // =================================================================
            $apiKey = Config::get('openai.api_key');
            $baseUri = Config::get('openai.base_uri');

            if (empty($apiKey) || empty($baseUri)) {
                throw new \Exception("OPENAI_API_KEY atau OPENAI_BASE_URI tidak ditemukan di config. Cek .env dan config/openai.php.");
            }
            if (!str_contains($baseUri, 'openrouter')) {
                throw new \Exception("Base URI tidak mengarah ke OpenRouter. Cek .env dan config/openai.php.");
            }

            $client = OpenAI::factory()
                ->withApiKey($apiKey)
                ->withBaseUri($baseUri)
                ->withHttpHeader('HTTP-Referer', 'https://ai-notula-app.test')
                ->withHttpHeader('X-Title', 'AI Notula App')
                ->make();

            Log::info("Membuat rangkuman dengan model OpenRouter: " . self::CHAT_MODEL);

            $summaryPrompt = 'You are a helpful assistant that summarizes meeting transcripts. Create a summary in well-structured HTML format. Use headings (<h3>), unordered lists (<ul><li>) for key points, and bold tags (<b>) to highlight action items or names. Here is the transcript: ' . $transcript;

            $summaryResponse = $client->chat()->create([
                'model' => self::CHAT_MODEL,
                'messages' => [
                    ['role' => 'user', 'content' => $summaryPrompt],
                ],
            ]);
            $summary = $summaryResponse->choices[0]->message->content;
            Log::info("Rangkuman berhasil dibuat.");

            // =================================================================
            // LANGKAH 3: UPDATE DATABASE
            // =================================================================
            $this->meeting->update([
                'transcript' => $transcript,
                'summary' => $summary,
                'status' => 'Selesai Diproses',
            ]);
            Log::info("Rapat ID: {$this->meeting->id} berhasil diproses dan disimpan.");

            // =================================================================
            // PERBAIKAN: Refresh data model dari database
            // =================================================================
            $this->meeting->refresh();

            // =================================================================
            // LANGKAH 4: KIRIM EMAIL KE SEMUA USER DI UNIT
            // =================================================================
            if (!$this->meeting->unit_id) {
                Log::warning("Tidak ada unit_id untuk Rapat ID: {$this->meeting->id}, email tidak dikirim.");
                return;
            }

            $usersInUnit = User::where('unit_id', $this->meeting->unit_id)
                                ->whereNotNull('email')
                                ->get();

            if ($usersInUnit->isEmpty()) {
                Log::warning("Tidak ada user ditemukan di unit ID {$this->meeting->unit_id}, email tidak dikirim.");
                return;
            }

            Log::info("Mengirim email hasil rapat ke {$usersInUnit->count()} pengguna di unit ID {$this->meeting->unit_id}.");

            foreach ($usersInUnit as $user) {
                try {
                    // Kirim Mailable MeetingSummary dengan data $meeting YANG SUDAH DIREfresh
                    Mail::to($user->email)->send(new MeetingSummary($this->meeting));
                    Log::info("Email hasil rapat terkirim ke {$user->email}");
                } catch (\Exception $e) {
                    Log::error("Gagal mengirim email hasil rapat ke {$user->email}: " . $e->getMessage());
                }
            }

        } catch (\Exception $e) {
            // Tangkap semua error, termasuk dari API
            $errorMessage = $e->getMessage();
            if ($e instanceof \OpenAI\Exceptions\ErrorException) {
                $errorMessage = "OpenAI API Error: " . $e->getMessage(); // Log error spesifik dari API
            }

            Log::error("Gagal memproses notula untuk Rapat ID {$this->meeting->id}", [
                'error_message' => $errorMessage,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString() // Tambahkan trace untuk debug
            ]);
            $this->meeting->update(['status' => 'Gagal']);
        }
    }
}

