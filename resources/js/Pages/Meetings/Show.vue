<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue'; // <-- Import Tombol Merah
import { Head, useForm, usePage, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
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
});

const page = usePage();
const authUserRole = computed(() => page.props.auth.user.role);
const flashSuccess = computed(() => page.props.flash?.success);

const convertToTask = (item) => {
    router.post(route('meetings.action-items.convert', { meeting: props.meeting.id, actionItem: item.id }), {}, {
        preserveScroll: true,
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
const inputType = ref('text'); // 'text', 'audio', 'file', 'image'

const form = useForm({
    type: 'text',
    text_input: '',
    audio_file: null,
    text_file: null,
    image_file: null,
});

const onFileChange = (event, fileType) => {
    // ... (fungsi ini tidak berubah)
    const file = event.target.files[0];
    if (!file) return;

    if (fileType === 'audio') {
        form.audio_file = file;
        form.text_file = null; // Reset file lain
        form.image_file = null;
    } else if (fileType === 'text') {
        form.text_file = file;
        form.audio_file = null; // Reset file lain
        form.image_file = null;
    } else if (fileType === 'image') {
        form.image_file = file;
        form.audio_file = null; // Reset file lain
        form.text_file = null;
    }
};

const submitProcess = () => {
    // ... (fungsi ini tidak berubah)
    form.type = inputType.value;
    form.post(route('meetings.process', props.meeting.id), {
        forceFormData: true,
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            const audioInput = document.getElementById('audio_input');
            if (audioInput) audioInput.value = '';
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
    // ... (fungsi ini tidak berubah)
    return props.meeting.source_file_path ? `/storage/${props.meeting.source_file_path}` : null;
});

const isAudioFile = computed(() => {
    // ... (fungsi ini tidak berubah)
    if (!props.meeting.source_file_path) return false;
    const extension = props.meeting.source_file_path.split('.').pop().toLowerCase();
    return ['mp3', 'wav', 'm4a'].includes(extension);
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
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ meeting.title }}</h2>

                <div class="flex items-center space-x-2">
                     <!-- Tombol Edit -->
                    <Link v-if="meeting.status === 'Dijadwalkan'" :href="route('meetings.edit', meeting.id)" as="button" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
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

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <!-- ... (Sisa template tidak berubah: flash message, info rapat, tab notula, form input, dll.) ... -->

                <div v-if="flashSuccess" class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ flashSuccess }}</span>
                </div>

                 <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-500 mb-6">
                     <div><span class="font-semibold text-gray-700">Tanggal:</span> {{ formattedDate }}</div>
                     <div>
                        <span class="font-semibold text-gray-700">Status:</span>
                        <span :class="{
                            'bg-blue-100 text-blue-800': meeting.status === 'Dijadwalkan',
                            'bg-yellow-100 text-yellow-800': meeting.status === 'Memproses',
                            'bg-green-100 text-green-800': meeting.status === 'Selesai Diproses',
                            'bg-red-100 text-red-800': meeting.status === 'Gagal'
                        }" class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                            {{ meeting.status }}
                        </span>
                     </div>
                 </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-2 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div v-if="meeting.status === 'Selesai Diproses'" class="p-6">

                            <!-- Audio Player Section -->
                            <div v-if="isAudioFile" class="mb-6">
                                <h3 class="text-lg font-semibold mb-2">Rekaman Audio</h3>
                                <audio controls class="w-full">
                                    <source :src="sourceFileUrl" type="audio/mpeg">
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
                                <h3 class="text-lg font-semibold mb-2">Action Items</h3>
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
                                        <span v-if="item.converted_to_task" class="shrink-0 text-xs font-medium text-green-700 bg-green-100 px-2 py-1 rounded-full">Sudah jadi Task</span>
                                        <button v-else @click="convertToTask(item)" class="shrink-0 text-xs font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-md">Jadikan Task</button>
                                    </li>
                                </ul>
                            </div>
                            <div v-show="activeTab === 'email_ai'">
                                <h3 class="text-lg font-semibold mb-2">Buat Email dengan AI</h3>

                                <div class="flex flex-wrap items-end gap-3 mb-4">
                                    <div>
                                        <InputLabel for="email_purpose" value="Jenis Email" />
                                        <select id="email_purpose" v-model="emailPurpose" class="mt-1 block w-56 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
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
                                        <textarea id="email_body" v-model="emailDraft.body" rows="10" class="mt-1 block w-full font-mono text-xs border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
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
                                                <input type="checkbox" :value="u.id" v-model="selectedRecipients" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
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
                                        <div :class="['inline-block max-w-[80%] px-3 py-2 rounded-lg text-sm', msg.role === 'user' ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-200 text-gray-800']">
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
                                    <button @click="inputType = 'text'" :class="['whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm', inputType === 'text' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">Input Teks</button>
                                    <button @click="inputType = 'audio'" :class="['whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm', inputType === 'audio' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">Upload Audio</button>
                                    <button @click="inputType = 'file'" :class="['whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm', inputType === 'file' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">Upload File Teks</button>
                                    <button @click="inputType = 'image'" :class="['whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm', inputType === 'image' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">Upload Gambar (OCR)</button>
                                </nav>
                            </div>

                            <form @submit.prevent="submitProcess">
                                <div v-show="inputType === 'text'">
                                    <textarea v-model="form.text_input" rows="10" placeholder="Ketik atau paste transkrip rapat di sini..." class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                                    <InputError class="mt-2" :message="form.errors.text_input" />
                                </div>

                                <div v-show="inputType === 'audio'">
                                    <label for="audio_input" class="block text-sm font-medium text-gray-700">File Rekaman Audio (.mp3, .wav, .m4a)</label>
                                    <input @change="onFileChange($event, 'audio')" id="audio_input" type="file" accept=".mp3,.wav,.m4a" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"/>
                                    <InputError class="mt-2" :message="form.errors.audio_file" />
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

                                <div class="flex items-center mt-6">
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
                            <p class="text-sm text-gray-500 mt-2">Notula Anda sedang dibuat oleh AI. Halaman ini akan diperbarui otomatis setelah selesai.</p>
                        </div>
                    </div>

                    <!-- Agenda & Peserta -->
                    <div class="lg:col-span-1">
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <h3 class="text-lg font-semibold mb-4 text-gray-900">Detail Rapat</h3>

                            <div class="mb-6">
                                <h4 class="font-medium text-gray-700">Agenda</h4>
                                <div class="mt-2 text-sm text-gray-600 prose max-w-none" v-html="meeting.agenda || '<p><i>Tidak ada agenda.</i></p>'"></div>
                            </div>

                            <div>
                                <h4 class="font-medium text-gray-700">Peserta</h4>
                                <div class="mt-2 text-sm text-gray-600 prose max-w-none whitespace-pre-wrap" v-text="meeting.participants || 'Tidak ada daftar peserta.'"></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

