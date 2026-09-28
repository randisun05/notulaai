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
    canEdit: { type: Boolean, default: false },
    canApprove: { type: Boolean, default: false },
});

const STATUS = {
    draf: { label: 'Draf', class: 'badge-gray' },
    diajukan: { label: 'Menunggu Pengesahan', class: 'badge-yellow' },
    disahkan: { label: 'Disahkan', class: 'badge-green' },
    dikembalikan: { label: 'Dikembalikan', class: 'badge-red' },
};

const m = props.minutes ?? {};
const form = useForm({
    number: m.number ?? '',
    location: m.location ?? '',
    time_range: m.time_range ?? '',
    chairperson_id: m.chairperson_id ?? '',
    chairperson_name: m.chairperson_name ?? '',
    chairperson_title: m.chairperson_title ?? '',
    minute_taker_id: m.minute_taker_id ?? '',
    attendees: m.attendees ?? '',
    agenda: m.agenda ?? '',
    opening: m.opening ?? '',
    discussion: (m.discussion ?? []).map((d) => ({ ...d })),
    decisions: [...(m.decisions ?? [])],
    closing: m.closing ?? '',
});
// Pimpinan dari luar sistem (mis. pejabat eselon lain) diketik manual.
const externalChair = ref(!m.chairperson_id && !!m.chairperson_name);

const readonly = computed(() => !props.canEdit);
const generating = ref(false);

const generate = () => {
    if (props.minutes && !confirm('Susun ulang isi notulen dengan AI? Isi Pembukaan, Pembahasan, Keputusan, dan Penutup akan diganti (identitas rapat tetap).')) return;
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
    if (!confirm('Ajukan notulen untuk disahkan? Setelah diajukan, notulen tidak bisa diubah kecuali dikembalikan.')) return;
    save(() => router.post(route('meetings.minutes.submit', props.meeting.id), {}, { preserveScroll: true }));
};

const approve = () => {
    if (confirm('Sahkan notulen ini? Setelah disahkan, notulen terkunci.')) {
        router.post(route('meetings.minutes.approve', props.meeting.id), {}, { preserveScroll: true });
    }
};
const returnNote = ref('');
const returnForRevision = () => {
    router.post(route('meetings.minutes.return', props.meeting.id), { note: returnNote.value }, { preserveScroll: true });
};

const formatDate = (value) => value ? new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '-';
const formatDateTime = (value) => value ? new Date(value).toLocaleString('id-ID', { dateStyle: 'long', timeStyle: 'short' }) : '-';
</script>

<template>
    <Head :title="`Notulen — ${meeting.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <Link :href="route('meetings.show', meeting.id)" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ meeting.title }}</Link>
                    <h2 class="page-heading">Notulen Resmi</h2>
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
                <!-- Belum ada notulen -->
                <div v-if="!minutes" class="card-padded text-center">
                    <p class="text-gray-700 mb-4">
                        Susun notulen resmi (format dinas: identitas rapat, pembukaan, pembahasan, keputusan, tindak lanjut,
                        penutup, dan pengesahan pimpinan) dari hasil rapat ini. AI menyusun drafnya; Anda memeriksa dan melengkapinya.
                    </p>
                    <p v-if="meeting.status !== 'Selesai Diproses'" class="text-sm text-amber-700">Notula rapat harus selesai diproses terlebih dahulu.</p>
                    <PrimaryButton v-else-if="canEdit" :disabled="generating" @click="generate">{{ generating ? 'Menyusun draf...' : 'Susun Draf dengan AI' }}</PrimaryButton>
                </div>

                <template v-else>
                    <!-- Status pengesahan -->
                    <div v-if="minutes.status === 'dikembalikan'" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        <strong>Dikembalikan untuk diperbaiki:</strong> {{ minutes.return_note }}
                    </div>
                    <div v-if="minutes.status === 'diajukan'" class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800">
                        Diajukan oleh {{ minutes.submitter?.name }} pada {{ formatDateTime(minutes.submitted_at) }}, menunggu pengesahan
                        {{ minutes.chairperson?.name ?? 'admin unit' }}.
                    </div>
                    <div v-if="minutes.status === 'disahkan'" class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                        Disahkan oleh {{ minutes.approver?.name }} pada {{ formatDateTime(minutes.approved_at) }}. Notulen terkunci.
                    </div>

                    <div v-if="canApprove" class="card-padded">
                        <h3 class="font-semibold text-gray-900 mb-2">Pengesahan</h3>
                        <p class="text-sm text-gray-600 mb-3">Periksa notulen di bawah (atau unduh PDF-nya), lalu sahkan atau kembalikan dengan catatan.</p>
                        <div class="flex flex-wrap items-start gap-3">
                            <PrimaryButton @click="approve">Sahkan Notulen</PrimaryButton>
                            <div class="flex-1 min-w-[16rem]">
                                <textarea v-model="returnNote" rows="2" class="form-input w-full text-sm" placeholder="Catatan perbaikan..."></textarea>
                                <DangerButton class="mt-2" :disabled="!returnNote.trim()" @click="returnForRevision">Kembalikan ke Notulis</DangerButton>
                            </div>
                        </div>
                    </div>

                    <form class="space-y-6" @submit.prevent="save()">
                        <fieldset :disabled="readonly" class="card-padded space-y-4">
                            <h3 class="font-semibold text-gray-900">Identitas Rapat</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="sm:col-span-2">
                                    <InputLabel value="Nomor Notulen" />
                                    <TextInput v-model="form.number" class="w-full" placeholder="mis. 005/123/Bag.Umum/IX/2026" />
                                    <InputError :message="form.errors.number" />
                                </div>
                                <div>
                                    <InputLabel value="Waktu" />
                                    <TextInput v-model="form.time_range" class="w-full" placeholder="09.00 – 11.30 WIB" />
                                </div>
                                <div>
                                    <InputLabel value="Tempat" />
                                    <TextInput v-model="form.location" class="w-full" placeholder="Ruang Rapat Lt. 2" />
                                </div>
                                <div>
                                    <InputLabel value="Pimpinan Rapat" />
                                    <select v-if="!externalChair" v-model="form.chairperson_id" class="form-select w-full">
                                        <option value="">— pilih —</option>
                                        <option v-for="u in unitUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                                    </select>
                                    <TextInput v-else v-model="form.chairperson_name" class="w-full" placeholder="Nama pimpinan rapat" />
                                    <label class="mt-1 flex items-center gap-2 text-xs text-gray-600">
                                        <input v-model="externalChair" type="checkbox" class="rounded border-gray-300" />
                                        Pimpinan bukan pengguna aplikasi (pengesahan oleh admin unit)
                                    </label>
                                    <InputError :message="form.errors.chairperson_id" />
                                </div>
                                <div>
                                    <InputLabel value="Jabatan Pimpinan" />
                                    <TextInput v-model="form.chairperson_title" class="w-full" placeholder="mis. Kepala Bagian Umum" />
                                </div>
                                <div>
                                    <InputLabel value="Notulis" />
                                    <select v-model="form.minute_taker_id" class="form-select w-full">
                                        <option value="">— pilih —</option>
                                        <option v-for="u in unitUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                                    </select>
                                </div>
                                <div class="sm:col-span-2">
                                    <InputLabel value="Peserta" />
                                    <textarea v-model="form.attendees" rows="3" class="form-input w-full" placeholder="Satu peserta per baris atau dipisah koma"></textarea>
                                </div>
                                <div class="sm:col-span-2">
                                    <InputLabel value="Acara" />
                                    <textarea v-model="form.agenda" rows="2" class="form-input w-full"></textarea>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset :disabled="readonly" class="card-padded space-y-5">
                            <div class="flex items-center justify-between">
                                <h3 class="font-semibold text-gray-900">Isi Notulen</h3>
                                <SecondaryButton v-if="canEdit" type="button" :disabled="generating" @click="generate">{{ generating ? 'Menyusun...' : 'Susun Ulang dengan AI' }}</SecondaryButton>
                            </div>

                            <div>
                                <InputLabel value="I. Pembukaan" />
                                <textarea v-model="form.opening" rows="2" class="form-input w-full"></textarea>
                            </div>

                            <div>
                                <InputLabel value="II. Pembahasan" />
                                <div v-for="(item, i) in form.discussion" :key="i" class="mb-3 rounded-md border border-gray-200 p-3">
                                    <div class="flex gap-2">
                                        <span class="pt-2 text-sm text-gray-500">{{ i + 1 }}.</span>
                                        <TextInput v-model="item.topic" class="flex-1" placeholder="Topik" />
                                        <button v-if="canEdit" type="button" class="text-sm text-red-600" @click="form.discussion.splice(i, 1)">Hapus</button>
                                    </div>
                                    <textarea v-model="item.notes" rows="3" class="form-input w-full mt-2" placeholder="Uraian pembahasan"></textarea>
                                </div>
                                <button v-if="canEdit" type="button" class="text-sm text-brand-600" @click="form.discussion.push({ topic: '', notes: '' })">+ Tambah topik</button>
                            </div>

                            <div>
                                <InputLabel value="III. Keputusan/Kesimpulan" />
                                <div v-for="(decision, i) in form.decisions" :key="i" class="mb-2 flex gap-2">
                                    <span class="pt-2 text-sm text-gray-500">{{ i + 1 }}.</span>
                                    <textarea v-model="form.decisions[i]" rows="2" class="form-input flex-1"></textarea>
                                    <button v-if="canEdit" type="button" class="text-sm text-red-600" @click="form.decisions.splice(i, 1)">Hapus</button>
                                </div>
                                <button v-if="canEdit" type="button" class="text-sm text-brand-600" @click="form.decisions.push('')">+ Tambah keputusan</button>
                            </div>

                            <div>
                                <InputLabel value="IV. Tindak Lanjut" />
                                <p class="form-hint mb-2">Diambil dari Action Items rapat — ubah di halaman rapat supaya tetap sama dengan Task.</p>
                                <table v-if="actionItems.length" class="min-w-full text-sm border border-gray-200">
                                    <thead class="bg-gray-50"><tr><th class="table-head-cell">Uraian</th><th class="table-head-cell">Penanggung Jawab</th><th class="table-head-cell">Tenggat</th></tr></thead>
                                    <tbody>
                                        <tr v-for="item in actionItems" :key="item.id" class="border-t">
                                            <td class="table-cell">{{ item.title }}</td>
                                            <td class="table-cell">{{ item.assignee_name || '-' }}</td>
                                            <td class="table-cell">{{ formatDate(item.deadline) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <p v-else class="text-sm text-gray-500">Tidak ada.</p>
                            </div>

                            <div>
                                <InputLabel value="V. Penutup" />
                                <textarea v-model="form.closing" rows="2" class="form-input w-full"></textarea>
                            </div>
                        </fieldset>

                        <div v-if="canEdit" class="flex flex-wrap items-center gap-3">
                            <PrimaryButton :disabled="form.processing">Simpan</PrimaryButton>
                            <SecondaryButton type="button" :disabled="form.processing" @click="submit">Simpan &amp; Ajukan Pengesahan</SecondaryButton>
                            <span v-if="form.recentlySuccessful" class="text-sm text-green-700">Tersimpan.</span>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
