<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    tasks: { type: Object, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Array, required: true },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

// "Done" hanya lewat approve(), "Review" hanya lewat "Ajukan untuk Review" di
// halaman detail (wajib bukti pengerjaan) — tidak ditawarkan di dropdown ini.
const selectableStatuses = computed(() => props.statuses.filter((s) => s !== 'Done' && s !== 'Review'));

const exportUrl = (routeName) => {
    const query = props.filters.status ? `?status=${encodeURIComponent(props.filters.status)}` : '';
    return route(routeName) + query;
};

const filterByStatus = (status) => {
    router.get(route('tasks.index'), { status: status || undefined }, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
};

const updateStatus = (task, status) => {
    router.patch(route('tasks.update-status', task.id), { status }, {
        preserveScroll: true,
        preserveState: true,
    });
};

const formattedDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};
</script>

<template>
    <Head title="Daftar Task" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap justify-between items-center gap-3">
                <div>
                    <h2 class="page-heading">Daftar Task</h2>
                    <p class="page-subheading">Task dari hasil AI maupun dibuat manual, dilacak sampai selesai.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link v-if="canManage" :href="route('tasks.create')" class="btn-primary">
                        + Tambah Task
                    </Link>
                    <Link :href="route('tasks.kanban')" class="btn-secondary">Kanban</Link>
                    <Link :href="route('tasks.calendar')" class="btn-secondary">Kalender</Link>
                    <a :href="exportUrl('tasks.export.pdf')" class="btn-secondary">PDF</a>
                    <a :href="exportUrl('tasks.export.excel')" class="btn-secondary">Excel</a>
                </div>
            </div>
        </template>

        <div class="page-shell">
            <div class="page-container">
                <div v-if="flashSuccess" class="alert-success">
                    {{ flashSuccess }}
                </div>

                <div class="card-padded">
                    <div class="flex flex-wrap gap-2 mb-6">
                        <button @click="filterByStatus(null)" :class="['px-3 py-1.5 rounded-full text-xs font-medium transition', !filters.status ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']">Semua</button>
                        <button v-for="status in statuses" :key="status" @click="filterByStatus(status)" :class="['px-3 py-1.5 rounded-full text-xs font-medium transition', filters.status === status ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']">{{ status }}</button>
                    </div>

                    <div class="table-wrap">
                        <table class="table-base">
                            <thead class="table-head">
                                <tr>
                                    <th scope="col" class="table-head-cell">Judul</th>
                                    <th scope="col" class="table-head-cell">Rapat Sumber</th>
                                    <th scope="col" class="table-head-cell">PIC</th>
                                    <th scope="col" class="table-head-cell">Deadline</th>
                                    <th scope="col" class="table-head-cell">Status</th>
                                </tr>
                            </thead>
                            <tbody class="table-body">
                                <tr v-if="tasks.data.length === 0">
                                    <td colspan="5" class="empty-state">
                                        Belum ada task. Buat manual lewat "Tambah Task", atau jadikan Action Item hasil AI sebagai Task.
                                    </td>
                                </tr>
                                <tr v-for="task in tasks.data" :key="task.id" class="table-row-hover">
                                    <td class="table-cell">
                                        <Link :href="route('tasks.show', task.id)" class="font-medium text-brand-600 hover:text-brand-800">{{ task.title }}</Link>
                                        <span v-if="task.is_overdue" class="badge-red ml-2">Terlambat</span>
                                        <span v-if="task.is_sla_breached" class="badge-orange ml-2">SLA</span>
                                    </td>
                                    <td class="table-cell whitespace-nowrap">
                                        <Link v-if="task.meeting" :href="route('meetings.show', task.meeting.id)" class="text-brand-600 hover:text-brand-800">{{ task.meeting.title }}</Link>
                                        <span v-else class="text-gray-400">-</span>
                                    </td>
                                    <td class="table-cell whitespace-nowrap">
                                        {{ task.assignee ? task.assignee.name : (task.assignee_name || 'Belum ditentukan') }}
                                    </td>
                                    <td class="table-cell whitespace-nowrap">
                                        {{ formattedDate(task.deadline) }}
                                    </td>
                                    <td class="table-cell whitespace-nowrap">
                                        <select :value="task.status" @change="updateStatus(task, $event.target.value)" :disabled="task.status === 'Done' && !canManage" :title="task.status === 'Done' && !canManage ? 'Hanya admin yang bisa membuka kembali task yang sudah selesai' : null" class="form-select text-xs py-1.5">
                                            <option v-for="status in selectableStatuses" :key="status" :value="status">{{ status }}</option>
                                            <option v-if="task.status === 'Review'" value="Review">Review</option>
                                            <option v-if="task.status === 'Done'" value="Done">Done</option>
                                        </select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6" v-if="tasks.links.length > 0">
                        <Pagination :links="tasks.links" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
