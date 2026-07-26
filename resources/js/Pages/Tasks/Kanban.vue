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

const priorityColor = (priority) => ({
    Urgent: 'bg-red-100 text-red-700',
    High: 'bg-orange-100 text-orange-700',
    Medium: 'bg-blue-100 text-blue-700',
    Low: 'bg-gray-100 text-gray-600',
}[priority] || 'bg-gray-100 text-gray-600');
</script>

<template>
    <Head title="Kanban Task" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Kanban Task</h2>
                <Link :href="route('tasks.index')" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                    Lihat Daftar
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-full mx-auto sm:px-6 lg:px-8">
                <div class="overflow-x-auto pb-4">
                    <div class="flex gap-4 min-w-max">
                        <div
                            v-for="status in statuses"
                            :key="status"
                            @dragover.prevent
                            @drop="onDrop(status)"
                            class="w-72 shrink-0 bg-gray-50 border border-gray-200 rounded-lg p-3"
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
                                    class="bg-white border border-gray-200 rounded-md p-3 shadow-sm cursor-move hover:shadow-md transition-shadow"
                                >
                                    <Link :href="route('tasks.show', task.id)" class="text-sm font-medium text-gray-900 hover:text-indigo-600">{{ task.title }}</Link>
                                    <div class="mt-2 flex items-center justify-between">
                                        <span :class="['text-xs px-2 py-0.5 rounded-full', priorityColor(task.priority)]">{{ task.priority }}</span>
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
