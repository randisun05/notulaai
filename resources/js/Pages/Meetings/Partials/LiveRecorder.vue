<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    meeting: { type: Object, required: true },
    live: { type: Object, default: null },
    markerTypes: { type: Object, default: () => ({}) },
});

const CHUNK_MS = 5000;

// --- Perekam (hanya perangkat yang menekan "Mulai") -------------------------------
const recording = ref(false);
const stopping = ref(false);
const error = ref('');
const elapsed = ref(0);
const pendingChunks = ref(0);
const offline = ref(false);

let recorder = null;
let stream = null;
let wakeLock = null;
let queue = [];
let sentBytes = 0;
let pumping = null;
let partStartedAt = 0;
let partOffsetSeconds = 0;
let tick = null;

const isLive = computed(() => props.meeting.status === 'Berlangsung');
const canResume = computed(() => isLive.value && !recording.value);

const pickMimeType = () => {
    // Chrome/Firefox: webm/opus; Safari (iPhone/iPad/Mac): mp4.
    const candidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4'];
    return candidates.find((type) => window.MediaRecorder?.isTypeSupported?.(type)) ?? '';
};

const formatDuration = (seconds) => {
    const s = Math.floor(seconds);
    return [Math.floor(s / 3600), Math.floor((s % 3600) / 60), s % 60].map((n) => String(n).padStart(2, '0')).join(':');
};

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const warnBeforeLeave = (event) => {
    if (recording.value || pendingChunks.value > 0) {
        event.preventDefault();
        event.returnValue = '';
    }
};

const keepScreenOn = async () => {
    try {
        wakeLock = await navigator.wakeLock?.request('screen');
    } catch {
        // Tidak didukung / ditolak: rekaman tetap jalan, layar mungkin mati sendiri.
    }
};
// Wake lock lepas saat tab disembunyikan; minta lagi saat kembali.
const onVisibility = () => {
    if (recording.value && document.visibilityState === 'visible') keepScreenOn();
};

// Kirim potongan berurutan; koneksi putus → coba terus (audio menunggu di memori).
const pump = () => {
    if (pumping) return pumping;
    pumping = (async () => {
        let retries = 0;
        while (queue.length) {
            const item = queue[0];
            try {
                const { data } = await axios.put(route('meetings.live.append', props.meeting.id), item.blob, {
                    headers: {
                        'Content-Type': 'application/octet-stream',
                        'X-Upload-Offset': sentBytes,
                        'X-Recording-Seconds': item.seconds.toFixed(3),
                    },
                });
                sentBytes = data.received_bytes;
                queue.shift();
                retries = 0;
                offline.value = false;
            } catch (e) {
                const code = e.response?.status;
                if (code === 409 && e.response.data?.received_bytes !== undefined) {
                    const serverBytes = e.response.data.received_bytes;
                    // Potongan ini ternyata sudah tersimpan (balasannya yang hilang).
                    if (serverBytes === sentBytes + item.blob.size) queue.shift();
                    sentBytes = serverBytes;
                    if (e.response.data.status && e.response.data.status !== 'Berlangsung') {
                        error.value = 'Rekaman sudah dihentikan dari perangkat lain.';
                        teardown();
                        queue = [];
                        break;
                    }
                    continue;
                }
                if (code === 403) {
                    error.value = e.response.data?.message || 'Perangkat lain sedang merekam rapat ini.';
                    teardown();
                    queue = [];
                    break;
                }
                offline.value = true;
                await sleep(Math.min(2 ** ++retries, 30) * 1000);
            } finally {
                pendingChunks.value = queue.length;
            }
        }
        pumping = null;
    })();
    return pumping;
};

const begin = async (resume) => {
    error.value = '';
    const mimeType = pickMimeType();
    if (!mimeType) {
        error.value = 'Browser ini tidak mendukung perekaman audio. Gunakan Chrome, Edge, Firefox, atau Safari terbaru.';
        return;
    }

    try {
        stream = await navigator.mediaDevices.getUserMedia({
            audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true, channelCount: 1 },
        });
    } catch {
        error.value = 'Izin mikrofon ditolak atau mikrofon tidak ditemukan.';
        return;
    }

    try {
        const endpoint = resume ? 'meetings.live.resume' : 'meetings.live.start';
        const { data } = await axios.post(route(endpoint, props.meeting.id), { mime_type: mimeType });
        sentBytes = data.received_bytes;
        partOffsetSeconds = data.recorded_seconds;
    } catch (e) {
        stream.getTracks().forEach((t) => t.stop());
        error.value = e.response?.data?.message || 'Gagal memulai rekaman.';
        return;
    }

    recorder = new MediaRecorder(stream, { mimeType, audioBitsPerSecond: 32000 });
    partStartedAt = performance.now();
    recorder.ondataavailable = (event) => {
        if (event.data.size > 0) {
            queue.push({ blob: event.data, seconds: (performance.now() - partStartedAt) / 1000 });
            pendingChunks.value = queue.length;
            pump();
        }
    };
    recorder.start(CHUNK_MS);
    recording.value = true;
    tick = setInterval(() => { elapsed.value = partOffsetSeconds + (performance.now() - partStartedAt) / 1000; }, 1000);

    window.addEventListener('beforeunload', warnBeforeLeave);
    document.addEventListener('visibilitychange', onVisibility);
    keepScreenOn();

    // Status jadi "Berlangsung" → halaman mulai polling transkrip.
    router.reload({ only: ['meeting', 'live', 'progress'] });
};

const teardown = () => {
    clearInterval(tick);
    if (recorder && recorder.state !== 'inactive') recorder.stop();
    stream?.getTracks().forEach((t) => t.stop());
    wakeLock?.release?.().catch(() => {});
    recording.value = false;
};

const takeOver = () => {
    if (confirm('Ambil alih perekaman ke perangkat ini? Perangkat yang sedang merekam akan berhenti mengirim audio.')) begin(true);
};

const finish = async () => {
    if (!confirm('Akhiri rekaman rapat? Notula akan dibuat dari rekaman yang sudah masuk.')) return;
    stopping.value = true;
    // Potongan audio terakhir dikirim oleh MediaRecorder saat stop.
    await new Promise((resolve) => {
        if (!recorder || recorder.state === 'inactive') {
            teardown();
            resolve();
            return;
        }
        recorder.onstop = resolve;
        teardown();
    });
    await sleep(50);
    while (queue.length || pumping) await (pump() ?? sleep(200));

    try {
        await axios.post(route('meetings.live.stop', props.meeting.id));
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal mengakhiri rekaman, coba lagi.';
        stopping.value = false;
        return;
    }
    window.removeEventListener('beforeunload', warnBeforeLeave);
    stopping.value = false;
    router.reload();
};

onUnmounted(() => {
    teardown();
    window.removeEventListener('beforeunload', warnBeforeLeave);
    document.removeEventListener('visibilitychange', onVisibility);
});

// --- Transkrip berjalan (semua anggota) -----------------------------------------
const transcriptBox = ref(null);
const followLatest = ref(true);
const segments = computed(() => props.live?.segments ?? []);
const markers = computed(() => props.live?.markers ?? []);
const speakers = computed(() => props.live?.speakers ?? []);

watch(() => segments.value.map((s) => s.status + (s.text?.length ?? 0)).join(), async () => {
    if (!followLatest.value) return;
    await nextTick();
    transcriptBox.value?.scrollTo({ top: transcriptBox.value.scrollHeight, behavior: 'smooth' });
});
const onScroll = () => {
    const box = transcriptBox.value;
    followLatest.value = box.scrollHeight - box.scrollTop - box.clientHeight < 40;
};

const markerNote = ref('');
const addMarker = async (type) => {
    try {
        await axios.post(route('meetings.live.markers', props.meeting.id), { type, note: markerNote.value || null });
        markerNote.value = '';
        router.reload({ only: ['live'] });
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal menambah penanda.';
    }
};

const renaming = ref({});
const rename = async (speaker) => {
    const to = (renaming.value[speaker.label] ?? '').trim();
    if (!to) return;
    try {
        await axios.put(route('meetings.speakers.rename', props.meeting.id), { from: speaker.name ?? speaker.label, to });
        renaming.value[speaker.label] = '';
        router.reload({ only: ['live'] });
    } catch (e) {
        error.value = e.response?.data?.errors?.to?.[0] || e.response?.data?.message || 'Gagal mengganti nama pembicara.';
    }
};
</script>

<template>
    <div class="p-6">
        <!-- Kontrol perekam -->
        <div class="flex flex-wrap items-center gap-3">
            <template v-if="recording">
                <span class="inline-flex items-center gap-2 text-sm font-semibold text-red-600">
                    <span class="h-3 w-3 rounded-full bg-red-600 animate-pulse"></span> Merekam {{ formatDuration(elapsed) }}
                </span>
                <span v-if="offline" class="text-xs text-amber-700 bg-amber-50 px-2 py-1 rounded">
                    Koneksi terputus — {{ pendingChunks }} potongan audio menunggu dikirim (jangan tutup tab)
                </span>
                <DangerButton class="ml-auto" :disabled="stopping" @click="finish">{{ stopping ? 'Mengirim sisa audio...' : 'Akhiri Rapat' }}</DangerButton>
            </template>
            <template v-else-if="canResume && live?.is_recorder">
                <span class="text-sm text-gray-700">Perekaman di perangkat ini terhenti (tab tertutup/dimuat ulang).</span>
                <PrimaryButton class="ml-auto" @click="begin(true)">Lanjutkan Rekaman</PrimaryButton>
            </template>
            <template v-else-if="canResume">
                <span class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <span class="h-2.5 w-2.5 rounded-full bg-red-600 animate-pulse"></span> Rapat sedang direkam dari perangkat lain.
                </span>
                <!-- Sengaja tidak menonjol: mengambil alih memutus perekam yang sedang jalan. -->
                <button type="button" class="ml-auto text-xs text-gray-500 underline hover:text-gray-700" @click="takeOver">Perangkat perekam mati? Ambil alih di sini</button>
            </template>
            <template v-else>
                <div class="text-sm text-gray-600 max-w-xl">
                    Letakkan laptop/HP di tengah meja, lalu tekan Mulai. Transkrip muncul bertahap (±1 menit di belakang
                    pembicara) dan bisa dibaca semua anggota unit dari halaman ini. Biarkan tab ini tetap terbuka selama rapat.
                </div>
                <PrimaryButton class="ml-auto" @click="begin(false)">Mulai Rekam Rapat</PrimaryButton>
            </template>
        </div>
        <InputError class="mt-2" :message="error" />

        <div v-if="live" class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Transkrip berjalan -->
            <div class="lg:col-span-2">
                <h3 class="text-sm font-semibold text-gray-900 mb-2">Transkrip Berjalan</h3>
                <div ref="transcriptBox" class="h-96 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-4 text-sm" @scroll="onScroll">
                    <p v-if="!segments.length" class="text-gray-500">Transkrip pertama muncul sekitar satu menit setelah rekaman dimulai.</p>
                    <div v-for="segment in segments" :key="segment.index">
                        <div class="text-xs font-mono text-gray-400 mb-1">{{ segment.label }}</div>
                        <p v-if="segment.status === 'done'" class="whitespace-pre-wrap text-gray-800">{{ segment.text || '(tidak ada ucapan)' }}</p>
                        <p v-else-if="segment.status === 'failed'" class="text-red-600">Bagian ini gagal ditranskrip.</p>
                        <p v-else class="text-gray-400 italic">Sedang ditranskrip...</p>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Penanda notulis -->
                <div v-if="isLive">
                    <h3 class="text-sm font-semibold text-gray-900 mb-2">Tandai Momen Ini</h3>
                    <input v-model="markerNote" type="text" maxlength="500" placeholder="Catatan singkat (opsional)" class="form-input w-full text-sm mb-2" />
                    <div class="flex flex-wrap gap-2">
                        <SecondaryButton v-for="(label, type) in markerTypes" :key="type" type="button" @click="addMarker(type)">{{ label }}</SecondaryButton>
                    </div>
                    <p class="form-hint">Momen yang ditandai wajib masuk ke notula sebagai keputusan / tindak lanjut.</p>
                </div>
                <div v-if="markers.length">
                    <h3 class="text-sm font-semibold text-gray-900 mb-2">Penanda</h3>
                    <ul class="space-y-1 text-sm">
                        <li v-for="marker in markers" :key="marker.id">
                            <span class="font-mono text-xs text-gray-400">{{ marker.label }}</span>
                            <span class="font-medium" :class="marker.type === 'keputusan' ? 'text-green-700' : 'text-blue-700'"> {{ markerTypes[marker.type] }}</span>
                            <span v-if="marker.note" class="text-gray-700">: {{ marker.note }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Nama pembicara -->
                <div v-if="speakers.length">
                    <h3 class="text-sm font-semibold text-gray-900 mb-2">Nama Pembicara</h3>
                    <p class="form-hint mb-2">Ganti label dari AI dengan nama sebenarnya; berlaku untuk seluruh transkrip.</p>
                    <div v-for="speaker in speakers" :key="speaker.label" class="mb-3">
                        <div class="text-sm font-medium text-gray-800">
                            {{ speaker.name ?? speaker.label }}
                            <span v-if="speaker.name" class="text-xs font-normal text-gray-400">({{ speaker.label }})</span>
                        </div>
                        <div class="flex items-center gap-2 mt-1">
                            <input v-model="renaming[speaker.label]" type="text" maxlength="60" placeholder="Ganti nama..." class="form-input text-sm min-w-0 flex-1 py-1" @keyup.enter="rename(speaker)" />
                            <SecondaryButton type="button" class="shrink-0" @click="rename(speaker)">Simpan</SecondaryButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
