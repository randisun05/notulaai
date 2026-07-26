<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    tasksByStatus: { type: Object, required: true },
    statuses: { type: Array, required: true },
});

const draggingTask = ref(null);

const onDragStart = (task) => {
    draggingTask.value = task;
};

const onDrop = (status) => {
    if (!draggingTask.value || draggingTask.value.status === status) {
        draggingTask.value = null;
        return;
    }

    router.patch(route('tasks.update-status', draggingTask.value.id), { status }, {
        preserveScroll: true,
    });

    draggingTask.value = null;
};

const formattedDate = (dateString) => {
    if (!dateString) return null;
    return new Date(dateString).toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
};

const priorityBadge = (priority) => ({
    Urgent: 'badge-red',
    High: 'badge-orange',
    Medium: 'badge-blue',
    Low: 'badge-gray',
}[priority] || 'badge-gray');
</script>

<template>
    <Head title="Kanban Task" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="page-heading">Kanban Task</h2>
                <div class="flex gap-2">
                    <Link :href="route('tasks.index')" class="btn-secondary">Daftar</Link>
                    <Link :href="route('tasks.calendar')" class="btn-secondary">Kalender</Link>
                </div>
            </div>
        </template>

        <div class="page-shell">
            <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                <div class="overflow-x-auto pb-4">
                    <div class="flex gap-4 min-w-max">
                        <div
                            v-for="status in statuses"
                            :key="status"
                            @dragover.prevent
                            @drop="onDrop(status)"
                            class="w-72 shrink-0 bg-gray-100/70 border border-gray-200 rounded-2xl p-3"
                        >
                            <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center justify-between">
                                {{ status }}
                                <span class="text-xs font-normal text-gray-400 bg-white border border-gray-200 rounded-full px-2 py-0.5">{{ tasksByStatus[status]?.length || 0 }}</span>
                            </h3>

                            <div class="space-y-2 min-h-[4rem]">
                                <div
                                    v-for="task in tasksByStatus[status]"
                                    :key="task.id"
                                    draggable="true"
                                    @dragstart="onDragStart(task)"
                                    class="card p-3 cursor-move hover:shadow-md transition-shadow"
                                >
                                    <Link :href="route('tasks.show', task.id)" class="text-sm font-medium text-gray-900 hover:text-brand-600">{{ task.title }}</Link>
                                    <div class="mt-1 flex gap-1" v-if="task.is_overdue || task.is_sla_breached">
                                        <span v-if="task.is_overdue" class="badge-red">Terlambat</span>
                                        <span v-if="task.is_sla_breached" class="badge-orange">SLA</span>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between">
                                        <span :class="priorityBadge(task.priority)">{{ task.priority }}</span>
                                        <span v-if="formattedDate(task.deadline)" class="text-xs text-gray-400">{{ formattedDate(task.deadline) }}</span>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">{{ task.assignee ? task.assignee.name : (task.assignee_name || 'Belum ditentukan') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
