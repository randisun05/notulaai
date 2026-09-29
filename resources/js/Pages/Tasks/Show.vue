<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    task: { type: Object, required: true },
    statuses: { type: Array, required: true },
    unitUsers: { type: Array, default: () => [] },
    canApprove: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
    canUpdate: { type: Boolean, default: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

// "Done" hanya lewat approve(), "Review" hanya lewat form Ajukan untuk Review
// (wajib bukti pengerjaan) — keduanya tidak ditawarkan di dropdown biasa.
const selectableStatuses = computed(() => props.statuses.filter((s) => s !== 'Done' && s !== 'Review'));
const canSubmitForReview = computed(() => !['Done', 'Cancelled', 'Review'].includes(props.task.status));

const updateStatus = (status) => {
    router.patch(route('tasks.update-status', props.task.id), { status }, {
        preserveScroll: true,
    });
};

const showReviewModal = ref(false);
const reviewForm = useForm({ note: '', attachments: [] });

const onReviewFilesChange = (event) => {
    reviewForm.attachments = Array.from(event.target.files);
};

const submitForReview = () => {
    reviewForm.post(route('tasks.submit-for-review', props.task.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            showReviewModal.value = false;
            reviewForm.reset();
        },
    });
};

const approveTask = () => {
    router.post(route('tasks.approve', props.task.id), {}, { preserveScroll: true });
};

const rejectForm = useForm({ reason: '' });
const rejectTask = () => {
    rejectForm.post(route('tasks.reject', props.task.id), {
        preserveScroll: true,
        onSuccess: () => rejectForm.reset(),
    });
};

const disposeForm = useForm({ to_user_id: '', note: '' });
const submitDisposition = () => {
    disposeForm.post(route('tasks.dispositions.store', props.task.id), {
        preserveScroll: true,
        onSuccess: () => disposeForm.reset(),
    });
};

const slaForm = useForm({ sla_hours: props.task.sla_hours });
const submitSla = () => {
    slaForm.patch(route('tasks.update-sla', props.task.id), {
        preserveScroll: true,
    });
};

const formattedDate = (dateString) => {
    if (!dateString) return 'Belum ditentukan';
    return new Date(dateString).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
};

const formattedDateTime = (value) => {
    return new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
};

const priorityBadge = (priority) => ({
    Urgent: 'badge-red',
    High: 'badge-orange',
    Medium: 'badge-blue',
    Low: 'badge-gray',
}[priority] || 'badge-gray');
</script>

<template>
    <Head :title="task.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-3 flex-wrap">
                    <h2 class="page-heading">{{ task.title }}</h2>
                    <span v-if="task.is_overdue" class="badge-red">Terlambat</span>
                    <span v-if="task.is_sla_breached" class="badge-orange">SLA Terlampaui</span>
                </div>
                <Link v-if="canManage" :href="route('tasks.edit', task.id)"><SecondaryButton type="button">Edit Task</SecondaryButton></Link>
            </div>
        </template>

        <div class="page-shell">
            <div class="page-container max-w-5xl">

                <div v-if="flashSuccess" class="alert-success">
                    {{ flashSuccess }}
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 card-padded">
                        <h3 class="card-title mb-4">Detail Task</h3>

                        <dl class="space-y-4">
                            <div v-if="task.description">
                                <dt class="text-sm font-medium text-gray-500">Deskripsi</dt>
                                <dd class="mt-1 text-sm text-gray-800 whitespace-pre-wrap">{{ task.description }}</dd>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">PIC</dt>
                                    <dd class="mt-1 text-sm text-gray-800">{{ task.assignee ? task.assignee.name : (task.assignee_name || 'Belum ditentukan') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Deadline</dt>
                                    <dd class="mt-1 text-sm text-gray-800">{{ formattedDate(task.deadline) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Prioritas</dt>
                                    <dd class="mt-1">
                                        <span :class="priorityBadge(task.priority)">{{ task.priority }}</span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Unit</dt>
                                    <dd class="mt-1 text-sm text-gray-800">{{ task.unit?.name || '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">SLA</dt>
                                    <dd class="mt-1 text-sm text-gray-800">{{ task.sla_hours ? `${task.sla_hours} jam sejak dibuat` : 'Tidak diatur' }}</dd>
                                </div>
                            </div>

                            <div v-if="task.meeting">
                                <dt class="text-sm font-medium text-gray-500">Rapat Sumber</dt>
                                <dd class="mt-1 text-sm">
                                    <Link :href="route('meetings.show', task.meeting.id)" class="text-brand-600 hover:text-brand-800">{{ task.meeting.title }}</Link>
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Dibuat oleh</dt>
                                <dd class="mt-1 text-sm text-gray-800">{{ task.creator?.name || 'Sistem (AI)' }}</dd>
                            </div>
                        </dl>

                        <div v-if="!canUpdate" class="mt-6 pt-6 border-t border-gray-100">
                            <p class="text-sm text-gray-600"><span class="font-medium">Status:</span> {{ task.status }}</p>
                            <p class="form-hint">Anda melihat Task unit lain (hanya baca).</p>
                        </div>
                        <div v-else class="mt-6 pt-6 border-t border-gray-100">
                            <label class="form-label">Status</label>
                            <div class="flex flex-wrap items-center gap-3">
                                <select :value="task.status" @change="updateStatus($event.target.value)" :disabled="task.status === 'Done' && !canManage" :title="task.status === 'Done' && !canManage ? 'Hanya admin yang bisa membuka kembali task yang sudah selesai' : null" class="form-select w-full sm:w-64">
                                    <option v-for="status in selectableStatuses" :key="status" :value="status">{{ status }}</option>
                                    <option v-if="task.status === 'Review'" value="Review">Review</option>
                                    <option v-if="task.status === 'Done'" value="Done">Done</option>
                                </select>
                                <PrimaryButton v-if="canSubmitForReview" @click="showReviewModal = true">Ajukan untuk Review</PrimaryButton>
                            </div>
                            <p v-if="task.status !== 'Done'" class="form-hint">Untuk menandai selesai: "Ajukan untuk Review" dengan bukti pengerjaan, lalu tunggu persetujuan.</p>
                        </div>

                        <div v-if="task.evidences?.length" class="mt-6 pt-6 border-t border-gray-100">
                            <h4 class="text-sm font-semibold text-gray-700 mb-2">Bukti Pengerjaan</h4>
                            <div class="space-y-3">
                                <div v-for="evidence in task.evidences" :key="evidence.id" class="text-sm border border-gray-100 rounded-xl p-3 bg-gray-50">
                                    <p class="text-xs text-gray-500 mb-1">
                                        <span class="font-medium text-gray-700">{{ evidence.user?.name || 'Sistem' }}</span>
                                        &middot; {{ formattedDateTime(evidence.created_at) }}
                                    </p>
                                    <p v-if="evidence.note" class="text-gray-800 whitespace-pre-wrap">{{ evidence.note }}</p>
                                    <ul v-if="evidence.attachments?.length" class="mt-2 space-y-1">
                                        <li v-for="file in evidence.attachments" :key="file.id">
                                            <a :href="`/storage/${file.file_path}`" target="_blank" class="btn-link text-xs">
                                                📎 {{ file.file_name }}
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div v-if="task.status === 'Review' && canApprove" class="mt-6 pt-6 border-t border-gray-100 bg-yellow-50 -mx-6 px-6 py-4 rounded-b-2xl">
                            <h4 class="text-sm font-semibold text-gray-700 mb-2">Task Menunggu Persetujuan</h4>
                            <div class="flex flex-wrap items-start gap-2">
                                <PrimaryButton @click="approveTask">Setujui &amp; Tandai Selesai</PrimaryButton>
                                <div class="flex-1 min-w-[12rem]">
                                    <input v-model="rejectForm.reason" type="text" placeholder="Alasan penolakan (opsional)" class="form-input" />
                                </div>
                                <button @click="rejectTask" type="button" class="btn-secondary !text-red-700 !border-red-300 hover:!bg-red-50">
                                    Tolak
                                </button>
                            </div>
                        </div>

                        <div v-if="canUpdate" class="mt-6 pt-6 border-t border-gray-100">
                            <h4 class="text-sm font-semibold text-gray-700 mb-2">Disposisikan Task</h4>
                            <form @submit.prevent="submitDisposition" class="flex flex-wrap items-start gap-2">
                                <div>
                                    <select v-model="disposeForm.to_user_id" class="form-select w-56">
                                        <option value="" disabled>Pilih penerima...</option>
                                        <option v-for="u in unitUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                                    </select>
                                    <InputError class="mt-1" :message="disposeForm.errors.to_user_id" />
                                </div>
                                <div class="flex-1 min-w-[12rem]">
                                    <input v-model="disposeForm.note" type="text" placeholder="Catatan (opsional)" class="form-input" />
                                    <InputError class="mt-1" :message="disposeForm.errors.note" />
                                </div>
                                <PrimaryButton :disabled="disposeForm.processing || !disposeForm.to_user_id">Disposisikan</PrimaryButton>
                            </form>

                            <div v-if="task.dispositions?.length" class="mt-4 space-y-2">
                                <div v-for="d in task.dispositions" :key="d.id" class="text-xs text-gray-500 border-l-2 border-gray-200 pl-2">
                                    <span class="font-medium text-gray-700">{{ d.from_user?.name || 'Sistem' }}</span> &rarr;
                                    <span class="font-medium text-gray-700">{{ d.to_user?.name }}</span>
                                    <span v-if="d.note"> &middot; "{{ d.note }}"</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="canUpdate" class="mt-6 pt-6 border-t border-gray-100">
                            <h4 class="text-sm font-semibold text-gray-700 mb-2">SLA (Batas Waktu Penyelesaian)</h4>
                            <form @submit.prevent="submitSla" class="flex items-start gap-2">
                                <div>
                                    <input v-model="slaForm.sla_hours" type="number" min="1" placeholder="Jam, mis. 48" class="form-input w-40" />
                                    <InputError class="mt-1" :message="slaForm.errors.sla_hours" />
                                </div>
                                <PrimaryButton :disabled="slaForm.processing">Simpan SLA</PrimaryButton>
                            </form>
                            <p class="form-hint">Task otomatis dieskalasi ke pembuat Task jika SLA terlampaui atau deadline lewat.</p>
                        </div>
                    </div>

                    <div class="card-padded">
                        <h3 class="card-title mb-4">Riwayat Task</h3>
                        <p v-if="!task.activities?.length" class="empty-state">Belum ada riwayat.</p>
                        <ol v-else class="space-y-4">
                            <li v-for="activity in task.activities" :key="activity.id">
                                <p class="text-sm text-gray-700">{{ activity.description }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ formattedDateTime(activity.created_at) }}</p>
                            </li>
                        </ol>
                    </div>
                </div>

            </div>
        </div>

        <Modal :show="showReviewModal" @close="showReviewModal = false">
            <form @submit.prevent="submitForReview" class="p-6">
                <h2 class="text-lg font-semibold text-gray-900">Ajukan untuk Review</h2>
                <p class="mt-1 text-sm text-gray-600">Lampirkan catatan dan/atau file sebagai bukti pengerjaan sebelum diajukan ke approver.</p>

                <div class="mt-4">
                    <label class="form-label">Catatan</label>
                    <textarea v-model="reviewForm.note" rows="4" class="form-textarea" placeholder="Ceritakan apa yang sudah dikerjakan..."></textarea>
                    <InputError class="mt-1" :message="reviewForm.errors.note" />
                </div>

                <div class="mt-4">
                    <label class="form-label">File Bukti (maks. 5 file, 10MB/file)</label>
                    <input type="file" multiple @change="onReviewFilesChange" class="mt-1 block w-full text-sm" />
                    <InputError class="mt-1" :message="reviewForm.errors.attachments" />
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton type="button" @click="showReviewModal = false">Batal</SecondaryButton>
                    <PrimaryButton class="ml-3" :class="{ 'opacity-50': reviewForm.processing }" :disabled="reviewForm.processing">
                        Ajukan
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
