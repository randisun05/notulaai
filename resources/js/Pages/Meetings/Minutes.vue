<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    meeting: { type: Object, required: true },
    minutes: { type: Object, default: null },
    actionItems: { type: Array, default: () => [] },
    unitUsers: { type: Array, default: () => [] },
    chairCandidates: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: false },
    canApprove: { type: Boolean, default: false },
});

const STATUS = {
    draf: { label: 'Draf', class: 'badge-gray' },
    diajukan: { label: 'Menunggu Persetujuan', class: 'badge-yellow' },
    disahkan: { label: 'Disetujui', class: 'badge-green' },
    dikembalikan: { label: 'Dikembalikan', class: 'badge-red' },
};

const m = props.minutes ?? {};
const form = useForm({
    number: m.number ?? '',
    title: m.title ?? '',
    location: m.location ?? '',
    time_range: m.time_range ?? '',
    chairperson_id: m.chairperson_id ?? '',
    chairperson_name: m.chairperson_name ?? '',
    chairperson_title: m.chairperson_title ?? '',
    minute_taker_id: m.minute_taker_id ?? '',
    attendees: m.attendees ?? '',
    agenda: m.agenda ?? '',
    resume: (m.resume ?? []).map((p) => ({ speaker: p.speaker ?? '', text: p.text ?? '', response: p.response ?? '' })),
    decisions: [...(m.decisions ?? [])],
    closing: m.closing ?? '',
});
// Pemimpin dari luar sistem (mis. pejabat unit lain) diketik manual.
const externalChair = ref(!m.chairperson_id && !!m.chairperson_name);

const readonly = computed(() => !props.canEdit);
const generating = ref(false);

const generate = () => {
    if (props.minutes && !confirm('Susun ulang Resume dengan AI? Poin-poin Resume dan Kesimpulan akan diganti (identitas rapat tetap).')) return;
    generating.value = true;
    router.post(route('meetings.minutes.generate', props.meeting.id), {}, { onFinish: () => { generating.value = false; } });
};

const save = (then) => {
    form.transform((data) => ({
        ...data,
        chairperson_id: externalChair.value ? null : (data.chairperson_id || null),
        chairperson_name: externalChair.value ? data.chairperson_name : null,
        minute_taker_id: data.minute_taker_id || null,
    })).put(route('meetings.minutes.update', props.meeting.id), { preserveScroll: true, onSuccess: then });
};

const submit = () => {
    if (!confirm('Ajukan notula untuk disetujui? Setelah diajukan, notula tidak bisa diubah kecuali dikembalikan.')) return;
    save(() => router.post(route('meetings.minutes.submit', props.meeting.id), {}, { preserveScroll: true }));
};

const approve = () => {
    if (confirm('Setujui notula ini? Setelah disetujui, notula terkunci.')) {
        router.post(route('meetings.minutes.approve', props.meeting.id), {}, { preserveScroll: true });
    }
};
const returnNote = ref('');
const returnForRevision = () => {
    router.post(route('meetings.minutes.return', props.meeting.id), { note: returnNote.value }, { preserveScroll: true });
};

// Resume: poin bisa digeser supaya urutannya sesuai jalannya rapat.
const movePoint = (i, delta) => {
    const j = i + delta;
    if (j < 0 || j >= form.resume.length) return;
    [form.resume[i], form.resume[j]] = [form.resume[j], form.resume[i]];
};

const photos = computed(() => props.minutes?.documentation ?? []);
const photoForm = useForm({ photos: [] });
const uploadPhotos = (event) => {
    photoForm.photos = Array.from(event.target.files);
    photoForm.post(route('meetings.minutes.photos.store', props.meeting.id), {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => { event.target.value = ''; },
    });
};
const deletePhoto = (index) => {
    if (confirm('Hapus foto dokumentasi ini?')) {
        router.delete(route('meetings.minutes.photos.destroy', [props.meeting.id, index]), { preserveScroll: true });
    }
};

const formatDate = (value) => value ? new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '-';
const formatDateTime = (value) => value ? new Date(value).toLocaleString('id-ID', { dateStyle: 'long', timeStyle: 'short' }) : '-';
</script>

<template>
    <Head :title="`Notula — ${meeting.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <Link :href="route('meetings.show', meeting.id)" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ meeting.title }}</Link>
                    <h2 class="page-heading">Notula Resmi</h2>
                </div>
                <div v-if="minutes" class="flex items-center gap-2">
                    <span :class="['badge', STATUS[minutes.status].class]">{{ STATUS[minutes.status].label }}</span>
                    <a :href="route('meetings.minutes.pdf', meeting.id)" class="btn-secondary">Unduh PDF</a>
                    <a :href="route('meetings.minutes.docx', meeting.id)" class="btn-secondary">Unduh Word</a>
                </div>
            </div>
        </template>

        <div class="page-shell">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Belum ada notula -->
                <div v-if="!minutes" class="card-padded text-center">
                    <p class="text-gray-700 mb-4">
                        Susun notula resmi rapat ini dalam format dinas: kop instansi, identitas rapat, Resume berupa
                        poin-poin (masukan tiap peserta beserta tanggapannya), dan tanda tangan notulen. AI menyusun drafnya;
                        Anda memeriksa dan melengkapinya.
                    </p>
                    <p v-if="meeting.status !== 'Selesai Diproses'" class="text-sm text-amber-700">Notula rapat harus selesai diproses terlebih dahulu.</p>
                    <PrimaryButton v-else-if="canEdit" :disabled="generating" @click="generate">{{ generating ? 'Menyusun draf...' : 'Susun Draf dengan AI' }}</PrimaryButton>
                </div>

                <template v-else>
                    <!-- Status persetujuan -->
                    <div v-if="minutes.status === 'dikembalikan'" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        <strong>Dikembalikan untuk diperbaiki:</strong> {{ minutes.return_note }}
                    </div>
                    <div v-if="minutes.status === 'diajukan'" class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800">
                        Diajukan oleh {{ minutes.submitter?.name }} pada {{ formatDateTime(minutes.submitted_at) }}, menunggu persetujuan
                        {{ minutes.chairperson?.name ?? 'admin unit' }}.
                    </div>
                    <div v-if="minutes.status === 'disahkan'" class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                        Disetujui oleh {{ minutes.approver?.name }} pada {{ formatDateTime(minutes.approved_at) }}. Notula terkunci.
                    </div>

                    <div v-if="canApprove" class="card-padded">
                        <h3 class="font-semibold text-gray-900 mb-2">Persetujuan</h3>
                        <p class="text-sm text-gray-600 mb-3">Periksa notula di bawah (atau unduh PDF-nya), lalu setujui atau kembalikan dengan catatan.</p>
                        <div class="flex flex-wrap items-start gap-3">
                            <PrimaryButton @click="approve">Setujui Notula</PrimaryButton>
                            <div class="flex-1 min-w-[16rem]">
                                <textarea v-model="returnNote" rows="2" class="form-input w-full text-sm" placeholder="Catatan perbaikan..."></textarea>
                                <DangerButton class="mt-2" :disabled="!returnNote.trim()" @click="returnForRevision">Kembalikan ke Notulen</DangerButton>
                            </div>
                        </div>
                    </div>

                    <form class="space-y-6" @submit.prevent="save()">
                        <fieldset :disabled="readonly" class="card-padded space-y-4">
                            <h3 class="font-semibold text-gray-900">Identitas Rapat</h3>
                            <div>
                                <InputLabel value="Judul Notula" />
                                <TextInput v-model="form.title" class="w-full" placeholder="mis. Rapat Pembahasan Rancangan Peraturan ..." />
                                <p class="form-hint">Ditulis dengan huruf kapital di bawah kata NOTULA.</p>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel value="Pukul" />
                                    <TextInput v-model="form.time_range" class="w-full" placeholder="09.00 – 12.00 WIB" />
                                </div>
                                <div>
                                    <InputLabel value="Tempat" />
                                    <TextInput v-model="form.location" class="w-full" placeholder="mis. Zoom Meeting / Ruang Rapat Lt. 2" />
                                </div>
                                <div>
                                    <InputLabel value="Pemimpin Rapat (nama)" />
                                    <select v-if="!externalChair" v-model="form.chairperson_id" class="form-select w-full">
                                        <option value="">— pilih —</option>
                                        <option v-for="u in chairCandidates" :key="u.id" :value="u.id">{{ u.name }}</option>
                                    </select>
                                    <TextInput v-else v-model="form.chairperson_name" class="w-full" placeholder="Nama pemimpin rapat" />
                                    <label class="mt-1 flex items-center gap-2 text-xs text-gray-600">
                                        <input v-model="externalChair" type="checkbox" class="rounded border-gray-300" />
                                        Bukan pengguna aplikasi (persetujuan oleh admin unit)
                                    </label>
                                    <InputError :message="form.errors.chairperson_id" />
                                </div>
                                <div>
                                    <InputLabel value="Pemimpin Rapat (jabatan)" />
                                    <TextInput v-model="form.chairperson_title" class="w-full" placeholder="mis. Direktur JF MASN" />
                                    <p class="form-hint">Jabatan inilah yang tercetak di notula.</p>
                                </div>
                                <div>
                                    <InputLabel value="Notulen" />
                                    <select v-model="form.minute_taker_id" class="form-select w-full">
                                        <option value="">— pilih —</option>
                                        <option v-for="u in unitUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                                    </select>
                                    <p class="form-hint">Nama & NIP (dari data pengguna) tercetak di tanda tangan.</p>
                                </div>
                                <div>
                                    <InputLabel value="Nomor (opsional)" />
                                    <TextInput v-model="form.number" class="w-full" placeholder="Kosongkan bila notula tidak bernomor" />
                                </div>
                                <div class="sm:col-span-2">
                                    <InputLabel value="Peserta Rapat" />
                                    <textarea v-model="form.attendees" rows="2" class="form-input w-full" placeholder="mis. Undangan dan Pegawai Direktorat JF MASN"></textarea>
                                </div>
                                <div class="sm:col-span-2">
                                    <InputLabel value="Acara (opsional)" />
                                    <textarea v-model="form.agenda" rows="2" class="form-input w-full"></textarea>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset :disabled="readonly" class="card-padded space-y-5">
                            <div class="flex items-center justify-between">
                                <h3 class="font-semibold text-gray-900">Resume</h3>
                                <SecondaryButton v-if="canEdit" type="button" :disabled="generating" @click="generate">{{ generating ? 'Menyusun...' : 'Susun Ulang dengan AI' }}</SecondaryButton>
                            </div>
                            <p class="form-hint -mt-3">Satu poin per jalannya rapat: pembukaan, lalu masukan/pertanyaan tiap peserta. Tanggapan dicetak dengan tanda &#10132;.</p>

                            <div v-for="(point, i) in form.resume" :key="i" class="rounded-md border border-gray-200 p-3 space-y-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-400">&bull;</span>
                                    <TextInput v-model="point.speaker" class="flex-1" placeholder="Pembicara (opsional), mis. Ika Meidyawati, Dit. Bangtarier" />
                                    <template v-if="canEdit">
                                        <button type="button" class="text-gray-400 hover:text-gray-700" title="Naik" @click="movePoint(i, -1)">&uarr;</button>
                                        <button type="button" class="text-gray-400 hover:text-gray-700" title="Turun" @click="movePoint(i, 1)">&darr;</button>
                                        <button type="button" class="text-sm text-red-600" @click="form.resume.splice(i, 1)">Hapus</button>
                                    </template>
                                </div>
                                <textarea v-model="point.text" rows="3" class="form-input w-full" placeholder="Isi masukan / pertanyaan / uraian"></textarea>
                                <div class="flex gap-2">
                                    <span class="pt-2 text-gray-500">&#10132;</span>
                                    <textarea v-model="point.response" rows="2" class="form-input flex-1" placeholder="Tanggapan / jawaban (opsional)"></textarea>
                                </div>
                            </div>
                            <button v-if="canEdit" type="button" class="text-sm text-brand-600" @click="form.resume.push({ speaker: '', text: '', response: '' })">+ Tambah poin</button>

                            <div>
                                <InputLabel value="Kesimpulan rapat (opsional)" />
                                <div v-for="(decision, i) in form.decisions" :key="i" class="mb-2 flex gap-2">
                                    <span class="pt-2 text-sm text-gray-500">-</span>
                                    <textarea v-model="form.decisions[i]" rows="2" class="form-input flex-1"></textarea>
                                    <button v-if="canEdit" type="button" class="text-sm text-red-600" @click="form.decisions.splice(i, 1)">Hapus</button>
                                </div>
                                <button v-if="canEdit" type="button" class="text-sm text-brand-600" @click="form.decisions.push('')">+ Tambah kesimpulan</button>
                            </div>

                            <div>
                                <InputLabel value="Tindak lanjut" />
                                <p class="form-hint mb-2">Diambil dari Action Items rapat — ubah di halaman rapat supaya tetap sama dengan Task.</p>
                                <ul v-if="actionItems.length" class="text-sm text-gray-700 list-disc pl-5 space-y-1">
                                    <li v-for="item in actionItems" :key="item.id">
                                        {{ item.title }}
                                        <span class="text-gray-500">({{ item.assignee_name || 'PIC belum ditentukan' }}{{ item.deadline ? '; tenggat ' + formatDate(item.deadline) : '' }})</span>
                                    </li>
                                </ul>
                                <p v-else class="text-sm text-gray-500">Tidak ada.</p>
                            </div>

                            <div>
                                <InputLabel value="Penutup" />
                                <textarea v-model="form.closing" rows="2" class="form-input w-full"></textarea>
                            </div>
                        </fieldset>

                        <div v-if="canEdit" class="flex flex-wrap items-center gap-3">
                            <PrimaryButton :disabled="form.processing">Simpan</PrimaryButton>
                            <SecondaryButton type="button" :disabled="form.processing" @click="submit">Simpan &amp; Ajukan Persetujuan</SecondaryButton>
                            <span v-if="form.recentlySuccessful" class="text-sm text-green-700">Tersimpan.</span>
                        </div>
                    </form>

                    <!-- Dokumentasi -->
                    <div class="card-padded">
                        <h3 class="font-semibold text-gray-900 mb-1">Dokumentasi</h3>
                        <p class="form-hint mb-3">Foto kegiatan / tangkapan layar rapat daring; dicetak di halaman DOKUMENTASI.</p>
                        <div v-if="photos.length" class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-3">
                            <div v-for="(photo, i) in photos" :key="photo" class="relative">
                                <img :src="route('meetings.minutes.photos.show', [meeting.id, i])" alt="" class="w-full h-32 object-cover rounded-md border border-gray-200" />
                                <button v-if="canEdit" type="button" class="absolute top-1 right-1 bg-white/90 rounded px-1.5 text-xs text-red-600" @click="deletePhoto(i)">Hapus</button>
                            </div>
                        </div>
                        <input v-if="canEdit" type="file" multiple accept="image/jpeg,image/png,image/webp" class="text-sm" :disabled="photoForm.processing" @change="uploadPhotos" />
                        <InputError :message="photoForm.errors.photos || photoForm.errors['photos.0']" />
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
