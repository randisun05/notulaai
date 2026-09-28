<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import axios from 'axios';
import { computed, onUnmounted, ref } from 'vue';

const props = defineProps({
    meetingId: { type: Number, required: true },
});
const emit = defineEmits(['done']);

const MAX_RETRIES = 8;

const file = ref(null);
const status = ref('idle'); // idle | uploading | retrying | error | completing | done
const sentBytes = ref(0);
const error = ref('');
const uploadId = ref(null);
let cancelled = false;
let startedAt = 0;
let startedFrom = 0;

const percent = computed(() => (file.value ? Math.floor((sentBytes.value / file.value.size) * 100) : 0));
const busy = computed(() => ['uploading', 'retrying', 'completing'].includes(status.value));

const formatBytes = (bytes) => {
    if (bytes >= 1024 ** 3) return `${(bytes / 1024 ** 3).toFixed(2)} GB`;
    return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
};

const eta = computed(() => {
    if (status.value !== 'uploading' || !file.value) return null;
    const elapsed = (Date.now() - startedAt) / 1000;
    const done = sentBytes.value - startedFrom;
    if (elapsed < 3 || done <= 0) return null;
    const seconds = Math.round(((file.value.size - sentBytes.value) / done) * elapsed);
    return seconds >= 60 ? `±${Math.ceil(seconds / 60)} menit lagi` : `±${seconds} detik lagi`;
});

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

// Jangan sampai tab tertutup tanpa sengaja di tengah upload berjam-jam.
const warnBeforeLeave = (event) => {
    if (busy.value) {
        event.preventDefault();
        event.returnValue = '';
    }
};
window.addEventListener('beforeunload', warnBeforeLeave);
onUnmounted(() => {
    cancelled = true;
    window.removeEventListener('beforeunload', warnBeforeLeave);
});

const onFileChange = (event) => {
    file.value = event.target.files[0] ?? null;
    status.value = 'idle';
    error.value = '';
    sentBytes.value = 0;
};

const errorMessage = (e) => e.response?.data?.message
    || Object.values(e.response?.data?.errors ?? {})[0]?.[0]
    || 'Koneksi terputus.';

const start = async () => {
    if (!file.value || busy.value) return;
    cancelled = false;
    error.value = '';
    status.value = 'uploading';

    let upload;
    try {
        // Server mengembalikan upload lama untuk file yang sama → otomatis lanjut.
        ({ data: upload } = await axios.post(route('meetings.recording-uploads.store', props.meetingId), {
            file_name: file.value.name,
            file_size: file.value.size,
        }));
    } catch (e) {
        status.value = 'error';
        error.value = errorMessage(e);
        return;
    }

    uploadId.value = upload.id;
    let offset = upload.received_bytes;
    sentBytes.value = offset;
    startedAt = Date.now();
    startedFrom = offset;
    let retries = 0;

    while (offset < file.value.size) {
        if (cancelled) return;
        const chunk = file.value.slice(offset, offset + upload.chunk_bytes);

        try {
            const { data } = await axios.put(route('recording-uploads.append', upload.id), chunk, {
                headers: { 'Content-Type': 'application/octet-stream', 'X-Upload-Offset': offset },
                onUploadProgress: (p) => { sentBytes.value = offset + p.loaded; },
            });
            offset = data.received_bytes;
            sentBytes.value = offset;
            retries = 0;
            status.value = 'uploading';
        } catch (e) {
            const code = e.response?.status;
            if (code === 409 && e.response.data?.received_bytes !== undefined) {
                // Posisi server berbeda (mis. respons sebelumnya hilang) — ikuti server.
                offset = e.response.data.received_bytes;
                continue;
            }
            if (code && code < 500 && code !== 429) {
                status.value = 'error';
                error.value = errorMessage(e);
                return;
            }
            if (++retries > MAX_RETRIES) {
                status.value = 'error';
                error.value = 'Koneksi terputus terlalu lama. Klik "Lanjutkan Upload" — upload diteruskan dari posisi terakhir.';
                return;
            }
            status.value = 'retrying';
            await sleep(Math.min(2 ** retries, 30) * 1000);
        }
    }

    status.value = 'completing';
    try {
        await axios.post(route('recording-uploads.complete', upload.id));
        status.value = 'done';
        emit('done');
    } catch (e) {
        status.value = 'error';
        error.value = errorMessage(e);
    }
};

const cancel = async () => {
    cancelled = true;
    if (uploadId.value) {
        try {
            await axios.delete(route('recording-uploads.destroy', uploadId.value));
        } catch {
            // Upload yang ditinggalkan dibersihkan otomatis oleh recordings:prune-uploads.
        }
    }
    uploadId.value = null;
    status.value = 'idle';
    sentBytes.value = 0;
};
</script>

<template>
    <div>
        <label for="audio_input" class="block text-sm font-medium text-gray-700">File Rekaman Audio/Video (.mp3, .wav, .m4a, .mp4, .webm, .ogg, .aac, .flac, .mov)</label>
        <p class="text-xs text-gray-500 mb-1">
            Rekaman berjam-jam didukung: file diunggah bertahap (kalau koneksi putus, pilih file yang sama lagi untuk melanjutkan),
            lalu dipecah per 10 menit dan ditranskrip. Untuk video (mis. rekaman Zoom) hanya audionya yang dipakai.
        </p>
        <input
            id="audio_input"
            type="file"
            :disabled="busy"
            accept=".mp3,.wav,.m4a,.mp4,.webm,.ogg,.oga,.opus,.aac,.flac,.mov,.mkv,audio/*,video/*"
            class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
            @change="onFileChange"
        />

        <div v-if="file && status !== 'idle'" class="mt-4">
            <div class="flex justify-between text-xs text-gray-600 mb-1">
                <span>
                    <template v-if="status === 'completing'">Upload selesai, memulai pemrosesan...</template>
                    <template v-else-if="status === 'retrying'">Koneksi terputus, mencoba lagi...</template>
                    <template v-else>{{ formatBytes(sentBytes) }} / {{ formatBytes(file.size) }}<span v-if="eta"> · {{ eta }}</span></template>
                </span>
                <span>{{ percent }}%</span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-2">
                <div class="h-2 rounded-full transition-all" :class="status === 'error' ? 'bg-red-500' : 'bg-brand-600'" :style="{ width: percent + '%' }"></div>
            </div>
        </div>

        <InputError class="mt-2" :message="error" />

        <div class="flex items-center gap-3 mt-6">
            <PrimaryButton type="button" :disabled="!file || busy" :class="{ 'opacity-25': !file || busy }" @click="start">
                {{ status === 'error' && sentBytes > 0 ? 'Lanjutkan Upload' : 'Unggah & Proses Notula' }}
            </PrimaryButton>
            <SecondaryButton v-if="busy || (status === 'error' && uploadId)" type="button" :disabled="status === 'completing'" @click="cancel">Batalkan</SecondaryButton>
        </div>
    </div>
</template>
