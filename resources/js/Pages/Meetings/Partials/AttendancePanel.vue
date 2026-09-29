<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { router, useForm } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import { computed, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    meeting: { type: Object, required: true },
    attendance: { type: Object, required: true },
    unitUsers: { type: Array, default: () => [] },
    // Semua anggota unit yang bisa membuka rapat boleh mengelola daftar hadir (MeetingPolicy::update).
    canManage: { type: Boolean, default: true },
});

const list = computed(() => props.attendance.list ?? []);

// QR layar penuh untuk proyektor/TV ruang rapat; daftar diperbarui tiap 10 detik.
const showQr = ref(false);
const qrDataUrl = ref('');
let refresher = null;
watch(() => props.attendance.url, async (url) => {
    qrDataUrl.value = await QRCode.toDataURL(url, { width: 720, margin: 1, errorCorrectionLevel: 'M' });
}, { immediate: true });
watch(showQr, (open) => {
    clearInterval(refresher);
    if (open) refresher = setInterval(() => router.reload({ only: ['attendance'], preserveScroll: true }), 10000);
});
onUnmounted(() => clearInterval(refresher));

const copied = ref(false);
const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(props.attendance.url);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    } catch { /* clipboard tidak tersedia */ }
};

const adding = ref(false);
const addForm = useForm({ user_id: '', name: '', position: '', organization: '' });
const add = () => {
    addForm.post(route('meetings.attendance.store', props.meeting.id), {
        preserveScroll: true,
        onSuccess: () => { addForm.reset(); adding.value = false; },
    });
};
const remove = (entry) => {
    if (confirm(`Hapus ${entry.name} dari daftar hadir?`)) {
        router.delete(route('meetings.attendance.destroy', [props.meeting.id, entry.id]), { preserveScroll: true });
    }
};
const toggle = () => router.post(route('meetings.attendance.toggle', props.meeting.id), {}, { preserveScroll: true });
const regenerate = () => {
    if (confirm('Buat QR baru? QR lama (termasuk yang sudah difoto/dibagikan) tidak bisa dipakai lagi.')) {
        router.post(route('meetings.attendance.regenerate', props.meeting.id), {}, { preserveScroll: true });
    }
};

const time = (value) => new Date(value).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
</script>

<template>
    <div class="card-padded">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Daftar Hadir</h3>
            <span class="text-sm text-gray-500">{{ list.length }} hadir</span>
        </div>
        <p class="mt-1 text-xs" :class="attendance.open ? 'text-green-700' : 'text-gray-500'">
            {{ attendance.open ? 'Terbuka — peserta memindai QR dengan HP.' : 'Ditutup.' }}
        </p>

        <div v-if="canManage" class="mt-3 flex flex-wrap gap-2">
            <PrimaryButton type="button" @click="showQr = true">Tampilkan QR</PrimaryButton>
            <SecondaryButton type="button" @click="copyLink">{{ copied ? 'Tersalin' : 'Salin Tautan' }}</SecondaryButton>
        </div>

        <ul v-if="list.length" class="mt-4 max-h-72 overflow-y-auto divide-y divide-gray-100 text-sm">
            <li v-for="(entry, i) in list" :key="entry.id" class="flex items-start gap-2 py-2">
                <span class="w-5 text-gray-400">{{ i + 1 }}.</span>
                <div class="flex-1 min-w-0">
                    <div class="font-medium text-gray-900">{{ entry.name }}</div>
                    <div class="text-xs text-gray-500 truncate">{{ [entry.position, entry.organization].filter(Boolean).join(' · ') || '—' }}</div>
                </div>
                <span class="text-xs text-gray-400" :title="entry.method === 'qr' ? 'Pindai QR' : 'Ditambahkan manual'">{{ time(entry.checked_in_at) }}</span>
                <button v-if="canManage" type="button" class="text-xs text-red-600" @click="remove(entry)">Hapus</button>
            </li>
        </ul>

        <div v-if="canManage" class="mt-4 border-t border-gray-100 pt-3 space-y-2">
            <button v-if="!adding" type="button" class="text-sm text-brand-600" @click="adding = true">+ Tambah manual</button>
            <form v-else class="space-y-2" @submit.prevent="add">
                <select v-model="addForm.user_id" class="form-select w-full text-sm">
                    <option value="">Tamu (isi nama di bawah)</option>
                    <option v-for="u in unitUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
                <template v-if="!addForm.user_id">
                    <TextInput v-model="addForm.name" class="w-full text-sm" placeholder="Nama" required />
                    <TextInput v-model="addForm.position" class="w-full text-sm" placeholder="Jabatan" />
                    <TextInput v-model="addForm.organization" class="w-full text-sm" placeholder="Unit / Instansi" />
                </template>
                <div class="flex gap-2">
                    <PrimaryButton :disabled="addForm.processing">Tambah</PrimaryButton>
                    <SecondaryButton type="button" @click="adding = false">Batal</SecondaryButton>
                </div>
            </form>
            <div class="flex flex-wrap gap-3 text-xs">
                <button type="button" class="text-gray-600 underline" @click="toggle">{{ attendance.open ? 'Tutup daftar hadir' : 'Buka kembali daftar hadir' }}</button>
                <button type="button" class="text-gray-600 underline" @click="regenerate">Buat QR baru</button>
            </div>
        </div>

        <!-- QR layar penuh -->
        <Teleport to="body">
            <div v-if="showQr" class="fixed inset-0 z-50 flex flex-col items-center justify-center bg-white p-6 text-center" @click.self="showQr = false">
                <p class="text-sm font-semibold uppercase tracking-wide text-brand-600">Daftar Hadir</p>
                <h2 class="mt-1 max-w-3xl text-2xl font-bold text-gray-900">{{ meeting.title }}</h2>
                <img :src="qrDataUrl" alt="QR daftar hadir" class="mt-6 w-[min(70vh,80vw)] h-auto" />
                <p class="mt-4 text-lg text-gray-700">Pindai dengan kamera HP untuk mengisi daftar hadir</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ list.length }} <span class="text-lg font-normal text-gray-500">peserta hadir</span></p>
                <p v-if="!attendance.open" class="mt-2 text-red-600">Daftar hadir sedang ditutup.</p>
                <button type="button" class="mt-6 text-sm text-gray-500 underline" @click="showQr = false">Tutup tampilan</button>
            </div>
        </Teleport>
    </div>
</template>
