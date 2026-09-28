<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue'; // <-- Import Tombol Merah
import ForumSection from '@/Pages/Meetings/Partials/ForumSection.vue';
import ActivityTimeline from '@/Pages/Meetings/Partials/ActivityTimeline.vue';
import RecordingUploader from '@/Pages/Meetings/Partials/RecordingUploader.vue';
import LiveRecorder from '@/Pages/Meetings/Partials/LiveRecorder.vue';
import { Head, useForm, usePage, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    meeting: {
        type: Object,
        required: true,
    },
    unitUsers: {
        type: Array,
        default: () => [],
    },
    emailPurposes: {
        type: Object,
        default: () => ({}),
    },
    progress: {
        type: Object,
        default: null,
    },
    live: {
        type: Object,
        default: null,
    },
    markerTypes: {
        type: Object,
        default: () => ({}),
    },
});

// Status yang datanya berubah terus di server: Memproses (progres) & Berlangsung (transkrip live).
const POLLED_STATUSES = ['Memproses', 'Berlangsung'];

// Selama Memproses/Berlangsung: ambil progres & transkrip live tiap 5 detik (partial
// reload, ringan); begitu status berubah, muat ulang halaman penuh.
let progressTimer = null;
const pollProgress = () => {
    router.reload({
        only: ['progress', 'live'],
        preserveScroll: true,
        onSuccess: (p) => {
            if (p.props.progress?.status !== props.meeting.status) {
                stopPolling();
                router.reload({ preserveScroll: true });
            }
        },
    });
};
const stopPolling = () => {
    if (progressTimer) clearInterval(progressTimer);
    progressTimer = null;
};
const startPolling = () => {
    if (!progressTimer && POLLED_STATUSES.includes(props.meeting.status)) {
        progressTimer = setInterval(pollProgress, 5000);
    }
};
onMounted(startPolling);
onUnmounted(stopPolling);


const progressLabel = computed(() => {
    const p = props.progress;
    if (!p || p.status !== 'Memproses') return 'Menyiapkan...';
    if (p.stage === 'transcribing' && p.total) return `Mentranskrip rekaman: ${p.done} dari ${p.total} bagian`;
    if (p.stage === 'summarizing') return 'Membuat rangkuman dan action items...';
    return 'Menyiapkan rekaman...';
});
const progressPercent = computed(() => {
    const p = props.progress;
    if (!p || !p.total) return null;
    // Transkripsi = 90% pekerjaan, rangkuman 10% terakhir.
    return p.stage === 'summarizing' ? 95 : Math.round((p.done / p.total) * 90);
});

const page = usePage();
const authUserRole = computed(() => page.props.auth.user.role);
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const convertToTask = (item) => {
    router.post(route('meetings.action-items.convert', { meeting: props.meeting.id, actionItem: item.id }), {}, {
        preserveScroll: true,
    });
};

const hasConvertedActionItem = computed(() => (props.meeting.action_items || []).some((item) => item.converted_to_task));
const regeneratingActionItems = ref(false);

const regenerateActionItems = () => {
    if (!confirm('Generate ulang Action Items? Daftar yang sekarang akan diganti dengan hasil baru dari transkrip.')) {
        return;
    }

    regeneratingActionItems.value = true;
    router.post(route('meetings.action-items.regenerate', props.meeting.id), {}, {
        preserveScroll: true,
        onFinish: () => { regeneratingActionItems.value = false; },
    });
};

const emailPurpose = ref(Object.keys(props.emailPurposes)[0] || 'follow_up');
const emailDraft = ref(null);
const emailGenerating = ref(false);
const emailError = ref('');
const selectedRecipients = ref([]);

const generateEmailDraft = async () => {
    emailGenerating.value = true;
    emailError.value = '';
    emailDraft.value = null;

    try {
        const { data } = await axios.post(route('meetings.emails.generate', props.meeting.id), {
            purpose: emailPurpose.value,
        });
        emailDraft.value = data;
    } catch (e) {
        emailError.value = e.response?.data?.error || 'Gagal membuat draft email.';
    } finally {
        emailGenerating.value = false;
    }
};

const emailSendForm = useForm({
    subject: '',
    body: '',
    recipient_ids: [],
});

const sendEmail = () => {
    emailSendForm.subject = emailDraft.value.subject;
    emailSendForm.body = emailDraft.value.body;
    emailSendForm.recipient_ids = selectedRecipients.value;
    emailSendForm.post(route('meetings.emails.send', props.meeting.id), {
        preserveScroll: true,
        onSuccess: () => {
            emailDraft.value = null;
            selectedRecipients.value = [];
        },
    });
};

const chatMessages = ref([...(props.meeting.chat_messages || [])]);
const chatQuestion = ref('');
const chatAsking = ref(false);
const chatError = ref('');

const askChat = async () => {
    const question = chatQuestion.value.trim();
    if (!question || chatAsking.value) return;

    chatMessages.value.push({ id: `local-${Date.now()}`, role: 'user', content: question });
    chatQuestion.value = '';
    chatAsking.value = true;
    chatError.value = '';

    try {
        const { data } = await axios.post(route('meetings.chat.store', props.meeting.id), { question });
        chatMessages.value.push(data);
    } catch (e) {
        chatError.value = e.response?.data?.error || 'Gagal mendapat jawaban dari AI.';
    } finally {
        chatAsking.value = false;
    }
};

const activeTab = ref(props.meeting.status === 'Selesai Diproses' ? 'summary' : 'input');
watch(() => props.meeting.status, (status) => {
    if (POLLED_STATUSES.includes(status)) {
        startPolling();
        return;
    }
    stopPolling();
    // Reload mempertahankan state komponen; pindahkan ke tab hasil begitu selesai.
    if (status === 'Selesai Diproses') activeTab.value = 'summary';
});
const inputType = ref('text'); // 'live', 'text', 'audio', 'file', 'image'
// Panel rekaman live tetap terpasang dari "Mulai" sampai rekaman diakhiri (status
// Dijadwalkan → Berlangsung tidak boleh meng-unmount MediaRecorder-nya).
const liveMode = computed(() => props.meeting.status === 'Berlangsung'
    || (['Dijadwalkan', 'Gagal'].includes(props.meeting.status) && inputType.value === 'live'));

const form = useForm({
    type: 'text',
    text_input: '',
    text_file: null,
    image_file: null,
});

// Rekaman audio/video tidak lewat form ini, tapi lewat RecordingUploader (upload bertahap).
const onFileChange = (event, fileType) => {
    const file = event.target.files[0];
    if (!file) return;

    if (fileType === 'text') {
        form.text_file = file;
        form.image_file = null; // Reset file lain
    } else if (fileType === 'image') {
        form.image_file = file;
        form.text_file = null; // Reset file lain
    }
};

const submitProcess = () => {
    form.type = inputType.value;
    form.post(route('meetings.process', props.meeting.id), {
        forceFormData: true,
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            const textFileInput = document.getElementById('text_file_input');
            if (textFileInput) textFileInput.value = '';
            const imageInput = document.getElementById('image_input');
            if (imageInput) imageInput.value = '';
        },
    });
};

const formattedDate = computed(() => {
    // ... (fungsi ini tidak berubah)
    return new Date(props.meeting.date).toLocaleDateString('id-ID', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
});

const sourceFileUrl = computed(() => {
    // Lewat route yang dicek hak aksesnya: rekaman disimpan di disk privat.
    return props.meeting.source_file_path ? route('meetings.recording', props.meeting.id) : null;
});

const isAudioFile = computed(() => {
    // ... (fungsi ini tidak berubah)
    if (!props.meeting.source_file_path) return false;
    const extension = props.meeting.source_file_path.split('.').pop().toLowerCase();
    return ['mp3', 'mpga', 'wav', 'm4a', 'm4b', 'mp4', 'mov', 'webm', 'weba', 'ogg', 'oga', 'opus', 'aac', 'flac', 'mkv', 'mka'].includes(extension);
});

// Fungsi untuk menghapus rapat
const deleteMeeting = () => {
    if (confirm('Apakah Anda yakin ingin menghapus rapat ini? File rekaman dan notula akan dihapus permanen.')) {
        router.delete(route('meetings.destroy', props.meeting.id));
    }
};

</script>

<template>
    <Head :title="meeting.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap gap-4 justify-between items-center">
                <h2 class="page-heading">{{ meeting.title }}</h2>

                <div class="flex items-center gap-2">
                     <!-- Tombol Edit -->
                    <Link v-if="meeting.status === 'Dijadwalkan'" :href="route('meetings.edit', meeting.id)" as="button" class="btn-secondary">
                        Edit Rapat
                    </Link>
                    <!-- Tombol Hapus (Hanya untuk Admin / Super Admin) -->
                    <DangerButton
                        v-if="authUserRole !== 'user'"
                        @click="deleteMeeting"
                    >
                        Hapus Rapat
                    </DangerButton>
                </div>

            </div>
        </template>

        <div class="page-shell">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div v-if="flashSuccess" class="alert-success mb-4">
                    <span class="block sm:inline">{{ flashSuccess }}</span>
                </div>

                <div v-if="flashError" class="alert-error mb-4" role="alert">
                    <span class="block sm:inline">{{ flashError }}</span>
                </div>

                 <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-500 mb-6">
                     <div><span class="font-semibold text-gray-700">Tanggal:</span> {{ formattedDate }}</div>
                     <div>
                        <span class="font-semibold text-gray-700">Status:</span>
                        <span :class="{
                            'bg-blue-100 text-blue-800': meeting.status === 'Dijadwalkan',
                            'bg-yellow-100 text-yellow-800': meeting.status === 'Memproses',
                            'bg-red-100 text-red-700': meeting.status === 'Berlangsung',
                            'bg-green-100 text-green-800': meeting.status === 'Selesai Diproses',
                            'bg-red-100 text-red-800': meeting.status === 'Gagal'
                        }" class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                            {{ meeting.status }}
                        </span>
                     </div>
                 </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-2 card">
                        <div v-if="liveMode">
                            <div v-if="meeting.status !== 'Berlangsung'" class="px-6 pt-6">
                                <h2 class="text-xl font-semibold mb-1">Rekam Rapat Langsung</h2>
                                <button type="button" class="text-sm text-gray-500 hover:text-gray-700" @click="inputType = 'text'">&larr; Pilih metode input lain</button>
                            </div>
                            <LiveRecorder :meeting="meeting" :live="live" :marker-types="markerTypes" />
                        </div>

                        <div v-else-if="meeting.status === 'Selesai Diproses'" class="p-6">

                            <!-- Audio Player Section -->
                            <div v-if="isAudioFile" class="mb-6">
                                <h3 class="text-lg font-semibold mb-2">Rekaman Audio</h3>
                                <audio controls class="w-full">
                                    <source :src="sourceFileUrl">
                                    Browser Anda tidak mendukung elemen audio.
                                </audio>
                            </div>

                            <div class="border-b border-gray-200 mb-4">
                                <nav class="-mb-px flex space-x-6" aria-label="Tabs">
                                    <button @click="activeTab = 'summary'" :class="['whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm', activeTab === 'summary' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">Rangkuman</button>
                                    <button @click="activeTab = 'transcript'" :class="['whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm', activeTab === 'transcript' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">Transkrip Penuh</button>
                                    <button @click="activeTab = 'action_items'" :class="['whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm', activeTab === 'action_items' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
                                        Action Items
                                        <span v-if="meeting.action_items?.length" class="ml-1 inline-flex items-center justify-center h-5 min-w-[1.25rem] px-1 rounded-full bg-blue-100 text-blue-700 text-xs font-semibold">{{ meeting.action_items.length }}</span>
                                    </button>
                                    <button @click="activeTab = 'email_ai'" :class="['whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm', activeTab === 'email_ai' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">Email AI</button>
                                    <button @click="activeTab = 'chat_ai'" :class="['whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm', activeTab === 'chat_ai' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">Tanya AI</button>
                                </nav>
                            </div>
                            <div v-show="activeTab === 'summary'">
                                <h3 class="text-lg font-semibold mb-2">Rangkuman Notula</h3>
                                <div class="prose max-w-none text-gray-700" v-html="meeting.summary || '<p>Rangkuman tidak tersedia.</p>'"></div>
                            </div>
                            <div v-show="activeTab === 'transcript'">
                                <h3 class="text-lg font-semibold mb-2">Transkrip Penuh</h3>
                                <div class="prose max-w-none text-gray-700 whitespace-pre-wrap" v-text="meeting.transcript || 'Transkrip tidak tersedia.'"></div>
                            </div>
                            <div v-show="activeTab === 'action_items'">
                                <div class="flex items-center justify-between mb-2">
                                    <h3 class="text-lg font-semibold">Action Items</h3>
                                    <button
                                        v-if="!hasConvertedActionItem"
                                        @click="regenerateActionItems"
                                        :disabled="regeneratingActionItems"
                                        class="text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded-md disabled:opacity-50"
                                    >
                                        {{ regeneratingActionItems ? 'Men-generate ulang...' : 'Generate Ulang' }}
                                    </button>
                                    <span v-else class="text-xs text-gray-400" title="Sudah ada action item yang dijadikan Task, tidak bisa generate ulang">
                                        Generate ulang tidak tersedia (sudah ada yang jadi Task)
                                    </span>
                                </div>
                                <p v-if="!meeting.action_items?.length" class="text-sm text-gray-500">Tidak ada action item yang terdeteksi AI dari rapat ini.</p>
                                <ul v-else class="divide-y divide-gray-200 border border-gray-200 rounded-md">
                                    <li v-for="item in meeting.action_items" :key="item.id" class="p-4 flex items-start justify-between gap-4">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">{{ item.title }}</p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                PIC: {{ item.assignee_name || 'Belum ditentukan' }}
                                                <span v-if="item.deadline"> &middot; Deadline: {{ item.deadline }}</span>
                                            </p>
                                        </div>
                                        <span v-if="item.converted_to_task" class="badge-green shrink-0">Sudah jadi Task</span>
                                        <button v-else @click="convertToTask(item)" class="shrink-0 text-xs font-medium text-brand-700 bg-brand-50 hover:bg-brand-100 px-3 py-1.5 rounded-md">Jadikan Task</button>
                                    </li>
                                </ul>
                            </div>
                            <div v-show="activeTab === 'email_ai'">
                                <h3 class="text-lg font-semibold mb-2">Buat Email dengan AI</h3>

                                <div class="flex flex-wrap items-end gap-3 mb-4">
                                    <div>
                                        <InputLabel for="email_purpose" value="Jenis Email" />
                                        <select id="email_purpose" v-model="emailPurpose" class="mt-1 block w-56 border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
                                            <option v-for="(label, key) in emailPurposes" :key="key" :value="key">{{ label }}</option>
                                        </select>
                                    </div>
                                    <PrimaryButton :disabled="emailGenerating" @click="generateEmailDraft">
                                        {{ emailGenerating ? 'Membuat draft...' : 'Generate Draft' }}
                                    </PrimaryButton>
                                </div>

                                <p v-if="emailError" class="text-sm text-red-600 mb-4">{{ emailError }}</p>

                                <div v-if="emailDraft" class="space-y-4 border border-gray-200 rounded-md p-4">
                                    <div>
                                        <InputLabel for="email_subject" value="Subjek" />
                                        <TextInput id="email_subject" type="text" class="mt-1 block w-full" v-model="emailDraft.subject" />
                                        <InputError class="mt-2" :message="emailSendForm.errors.subject" />
                                    </div>
                                    <div>
                                        <InputLabel for="email_body" value="Isi Email (HTML)" />
                                        <textarea id="email_body" v-model="emailDraft.body" rows="10" class="mt-1 block w-full font-mono text-xs border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm"></textarea>
                                        <InputError class="mt-2" :message="emailSendForm.errors.body" />
                                    </div>
                                    <div>
                                        <InputLabel value="Preview" />
                                        <div class="mt-1 p-4 border border-gray-100 rounded-md bg-gray-50 prose max-w-none text-sm" v-html="emailDraft.body"></div>
                                    </div>
                                    <div>
                                        <InputLabel value="Kirim Ke" />
                                        <div class="mt-2 space-y-1 max-h-40 overflow-y-auto">
                                            <label v-for="u in unitUsers" :key="u.id" class="flex items-center gap-2 text-sm text-gray-700">
                                                <input type="checkbox" :value="u.id" v-model="selectedRecipients" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                                {{ u.name }} ({{ u.email }})
                                            </label>
                                        </div>
                                        <InputError class="mt-2" :message="emailSendForm.errors.recipient_ids" />
                                    </div>
                                    <PrimaryButton :disabled="emailSendForm.processing || selectedRecipients.length === 0" @click="sendEmail">
                                        Kirim Email
                                    </PrimaryButton>
                                </div>
                            </div>
                            <div v-show="activeTab === 'chat_ai'">
                                <h3 class="text-lg font-semibold mb-2">Tanya AI Tentang Rapat Ini</h3>
                                <p class="text-sm text-gray-500 mb-4">Contoh: "Apa keputusan rapat?", "Siapa PIC-nya?", "Apa saja deadline-nya?"</p>

                                <div class="border border-gray-200 rounded-md p-4 h-80 overflow-y-auto space-y-3 mb-4 bg-gray-50">
                                    <p v-if="chatMessages.length === 0" class="text-sm text-gray-400 text-center mt-10">Belum ada percakapan. Silakan mulai bertanya di bawah.</p>
                                    <div v-for="msg in chatMessages" :key="msg.id" :class="msg.role === 'user' ? 'text-right' : 'text-left'">
                                        <div :class="['inline-block max-w-[80%] px-3 py-2 rounded-lg text-sm', msg.role === 'user' ? 'bg-brand-600 text-white' : 'bg-white border border-gray-200 text-gray-800']">
                                            {{ msg.content }}
                                        </div>
                                    </div>
                                    <div v-if="chatAsking" class="text-left">
                                        <div class="inline-block px-3 py-2 rounded-lg text-sm bg-white border border-gray-200 text-gray-400 italic">AI sedang mengetik...</div>
                                    </div>
                                </div>

                                <p v-if="chatError" class="text-sm text-red-600 mb-2">{{ chatError }}</p>

                                <form @submit.prevent="askChat" class="flex gap-2">
                                    <TextInput type="text" class="block w-full" v-model="chatQuestion" placeholder="Ketik pertanyaan Anda..." :disabled="chatAsking" />
                                    <PrimaryButton :disabled="chatAsking || !chatQuestion.trim()">Kirim</PrimaryButton>
                                </form>
                            </div>
                        </div>

                        <div v-else-if="meeting.status === 'Dijadwalkan' || meeting.status === 'Gagal'" class="p-6">
                            <h2 class="text-xl font-semibold mb-2">Buat Notula Otomatis</h2>
                            <p class="text-sm text-gray-600 mb-6">Pilih metode input untuk membuat transkrip dan rangkuman.</p>

                            <div class="border-b border-gray-200 mb-6">
                                <nav class="-mb-px flex space-x-6" aria-label="Tabs">
                                    <button @click="inputType = 'live'" class="whitespace-nowrap pb-4 px-1 border-b-2 border-transparent font-medium text-sm text-red-600 hover:text-red-700">&#9679; Rekam Langsung</button>
                                    <button @click="inputType = 'text'" :class="['whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm', inputType === 'text' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">Input Teks</button>
                                    <button @click="inputType = 'audio'" :class="['whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm', inputType === 'audio' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">Upload Audio</button>
                                    <button @click="inputType = 'file'" :class="['whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm', inputType === 'file' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">Upload File Teks</button>
                                    <button @click="inputType = 'image'" :class="['whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm', inputType === 'image' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">Upload Gambar (OCR)</button>
                                </nav>
                            </div>

                            <form @submit.prevent="submitProcess">
                                <div v-show="inputType === 'text'">
                                    <textarea v-model="form.text_input" rows="10" placeholder="Ketik atau paste transkrip rapat di sini..." class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm"></textarea>
                                    <InputError class="mt-2" :message="form.errors.text_input" />
                                </div>

                                <div v-if="inputType === 'audio'">
                                    <RecordingUploader :meeting-id="meeting.id" @done="router.reload()" />
                                </div>

                                <div v-show="inputType === 'file'">
                                     <label for="text_file_input" class="block text-sm font-medium text-gray-700">File Teks (.txt, .md)</label>
                                    <input @change="onFileChange($event, 'text')" id="text_file_input" type="file" accept=".txt,.md" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"/>
                                    <InputError class="mt-2" :message="form.errors.text_file" />
                                </div>

                                <div v-show="inputType === 'image'">
                                     <label for="image_input" class="block text-sm font-medium text-gray-700">Foto Catatan/Papan Tulis (.jpg, .png, .webp)</label>
                                     <p class="text-xs text-gray-500 mb-1">Teks pada gambar akan dibaca otomatis oleh AI (OCR) lalu dirangkum.</p>
                                    <input @change="onFileChange($event, 'image')" id="image_input" type="file" accept=".jpg,.jpeg,.png,.webp" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"/>
                                    <InputError class="mt-2" :message="form.errors.image_file" />
                                </div>

                                <div v-if="form.progress && inputType !== 'audio'" class="mt-4">
                                    <div class="flex justify-between text-xs text-gray-600 mb-1"><span>Mengunggah...</span><span>{{ form.progress.percentage }}%</span></div>
                                    <div class="w-full bg-gray-100 rounded-full h-2"><div class="bg-brand-600 h-2 rounded-full transition-all" :style="{ width: form.progress.percentage + '%' }"></div></div>
                                </div>

                                <div v-if="inputType !== 'audio'" class="flex items-center mt-6">
                                    <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                        Proses Notula
                                    </PrimaryButton>
                                </div>
                            </form>
                        </div>

                        <div v-else-if="meeting.status === 'Memproses'" class="p-6 text-center">
                             <div class="flex justify-center items-center h-40">
                                <div class="animate-spin rounded-full h-16 w-16 border-b-2 border-blue-500"></div>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800">Sedang Memproses...</h3>
                            <p class="text-sm text-gray-700 mt-2">{{ progressLabel }}</p>
                            <div v-if="progressPercent !== null" class="max-w-md mx-auto mt-3">
                                <div class="w-full bg-gray-100 rounded-full h-2"><div class="bg-brand-600 h-2 rounded-full transition-all duration-700" :style="{ width: progressPercent + '%' }"></div></div>
                            </div>
                            <p class="text-sm text-gray-500 mt-3">Notula Anda sedang dibuat oleh AI. Halaman ini diperbarui otomatis — boleh ditinggal dan dibuka lagi nanti.</p>
                        </div>
                    </div>

                    <!-- Agenda & Peserta -->
                    <div class="lg:col-span-1">
                        <div class="card-padded">
                            <h3 class="text-lg font-semibold mb-4 text-gray-900">Detail Rapat</h3>

                            <div class="mb-6">
                                <h4 class="font-medium text-gray-700">Agenda</h4>
                                <div class="mt-2 text-sm text-gray-600 prose max-w-none" v-html="meeting.agenda || '<p><i>Tidak ada agenda.</i></p>'"></div>
                            </div>

                            <div>
                                <h4 class="font-medium text-gray-700">Peserta</h4>
                                <div class="mt-2 text-sm text-gray-600 prose max-w-none whitespace-pre-wrap" v-text="meeting.attendees || 'Tidak ada daftar peserta.'"></div>
                            </div>
                        </div>

                        <div class="mt-8">
                            <ActivityTimeline :meeting="meeting" />
                        </div>
                    </div>
                </div>

                <div class="mt-8">
                    <ForumSection :meeting="meeting" />
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

