<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    task: { type: Object, required: true },
    statuses: { type: Array, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const updateStatus = (status) => {
    router.patch(route('tasks.update-status', props.task.id), { status }, {
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

const priorityColor = (priority) => ({
    Urgent: 'bg-red-100 text-red-700',
    High: 'bg-orange-100 text-orange-700',
    Medium: 'bg-blue-100 text-blue-700',
    Low: 'bg-gray-100 text-gray-600',
}[priority] || 'bg-gray-100 text-gray-600');
</script>

<template>
    <Head :title="task.title" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ task.title }}</h2>
        </template>

        <div class="py-12">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <div v-if="flashSuccess" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    {{ flashSuccess }}
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-4 text-gray-900">Detail Task</h3>

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
                                        <span :class="['text-xs px-2 py-0.5 rounded-full', priorityColor(task.priority)]">{{ task.priority }}</span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Unit</dt>
                                    <dd class="mt-1 text-sm text-gray-800">{{ task.unit?.name || '-' }}</dd>
                                </div>
                            </div>

                            <div v-if="task.meeting">
                                <dt class="text-sm font-medium text-gray-500">Rapat Sumber</dt>
                                <dd class="mt-1 text-sm">
                                    <Link :href="route('meetings.show', task.meeting.id)" class="text-indigo-600 hover:text-indigo-900">{{ task.meeting.title }}</Link>
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Dibuat oleh</dt>
                                <dd class="mt-1 text-sm text-gray-800">{{ task.creator?.name || 'Sistem (AI)' }}</dd>
                            </div>
                        </dl>

                        <div class="mt-6 pt-6 border-t border-gray-100">
                            <label class="text-sm font-medium text-gray-500">Status</label>
                            <select :value="task.status" @change="updateStatus($event.target.value)" class="mt-1 block w-full sm:w-64 border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500">
                                <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-4 text-gray-900">Riwayat Task</h3>
                        <p v-if="!task.activities?.length" class="text-sm text-gray-500">Belum ada riwayat.</p>
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
    </AuthenticatedLayout>
</template>
