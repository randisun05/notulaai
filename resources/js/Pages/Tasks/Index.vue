<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    tasks: { type: Object, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Array, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

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
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Daftar Task</h2>
                <div class="flex gap-2">
                    <Link :href="route('tasks.kanban')" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                        Lihat Kanban
                    </Link>
                    <Link :href="route('tasks.calendar')" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                        Lihat Kalender
                    </Link>
                    <a :href="exportUrl('tasks.export.pdf')" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                        Export PDF
                    </a>
                    <a :href="exportUrl('tasks.export.excel')" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                        Export Excel
                    </a>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div v-if="flashSuccess" class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ flashSuccess }}</span>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">

                        <p class="text-sm text-gray-500 mb-4">Task di halaman ini dibuat dari Action Items hasil AI pada notula rapat.</p>

                        <div class="flex flex-wrap gap-2 mb-6">
                            <button @click="filterByStatus(null)" :class="['px-3 py-1.5 rounded-full text-xs font-medium', !filters.status ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']">Semua</button>
                            <button v-for="status in statuses" :key="status" @click="filterByStatus(status)" :class="['px-3 py-1.5 rounded-full text-xs font-medium', filters.status === status ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']">{{ status }}</button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Judul</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rapat Sumber</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PIC</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deadline</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-if="tasks.data.length === 0">
                                        <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                            Belum ada task. Buka sebuah rapat yang sudah diproses, lalu jadikan Action Item sebagai Task.
                                        </td>
                                    </tr>
                                    <tr v-for="task in tasks.data" :key="task.id">
                                        <td class="px-6 py-4">
                                            <Link :href="route('tasks.show', task.id)" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">{{ task.title }}</Link>
                                            <span v-if="task.is_overdue" class="ml-2 text-xs font-semibold px-1.5 py-0.5 rounded-full bg-red-100 text-red-700">Terlambat</span>
                                            <span v-if="task.is_sla_breached" class="ml-2 text-xs font-semibold px-1.5 py-0.5 rounded-full bg-orange-100 text-orange-700">SLA</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <Link v-if="task.meeting" :href="route('meetings.show', task.meeting.id)" class="text-indigo-600 hover:text-indigo-900">{{ task.meeting.title }}</Link>
                                            <span v-else>-</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ task.assignee ? task.assignee.name : (task.assignee_name || 'Belum ditentukan') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ formattedDate(task.deadline) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <select :value="task.status" @change="updateStatus(task, $event.target.value)" class="text-xs border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500">
                                                <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
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
        </div>
    </AuthenticatedLayout>
</template>
