<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AnalyticsNav from '@/Pages/Analytics/Partials/AnalyticsNav.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    overdueTasks: { type: Array, required: true },
    byUnit: { type: Array, required: true },
    ageBuckets: { type: Array, required: true },
    isSuperadmin: { type: Boolean, default: false },
});

const maxBucket = computed(() => Math.max(1, ...props.ageBuckets.map((b) => b.count)));

const severityColor = (daysOverdue) => {
    if (daysOverdue > 30) return 'text-[#d03b3b] font-semibold';
    if (daysOverdue > 7) return 'text-[#ec835a] font-medium';
    return 'text-gray-700';
};

const priorityColor = (priority) => ({
    Urgent: 'bg-red-100 text-red-700',
    High: 'bg-orange-100 text-orange-700',
    Medium: 'bg-blue-100 text-blue-700',
    Low: 'bg-gray-100 text-gray-600',
}[priority] || 'bg-gray-100 text-gray-600');
</script>

<template>
    <Head title="Overdue Analysis" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Overdue Analysis</h2>
        </template>

        <div class="py-12">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                <AnalyticsNav />

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Distribusi umur overdue -->
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4">Distribusi Usia Overdue</h3>
                        <div v-if="overdueTasks.length === 0" class="text-sm text-gray-400 py-6 text-center">Tidak ada task overdue. 🎉</div>
                        <div v-else class="space-y-2.5">
                            <div v-for="b in ageBuckets" :key="b.label" class="flex items-center gap-2">
                                <span class="w-20 text-xs text-gray-500 shrink-0">{{ b.label }}</span>
                                <div class="flex-1 bg-gray-100 rounded-full h-2.5 overflow-hidden">
                                    <div class="h-full rounded-full bg-[#d03b3b]" :style="{ width: `${(b.count / maxBucket) * 100}%` }"></div>
                                </div>
                                <span class="w-6 text-xs text-gray-600 text-right shrink-0">{{ b.count }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Breakdown per unit (superadmin) -->
                    <div v-if="isSuperadmin" class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4">Overdue per Unit</h3>
                        <div v-if="byUnit.length === 0" class="text-sm text-gray-400 py-6 text-center">Tidak ada data.</div>
                        <ul v-else class="space-y-2">
                            <li v-for="u in byUnit" :key="u.unit" class="flex items-center justify-between text-sm">
                                <span class="text-gray-600">{{ u.unit }}</span>
                                <span class="font-medium text-gray-900">{{ u.count }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PIC</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prioritas</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deadline</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Terlambat</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-if="overdueTasks.length === 0">
                                    <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">Tidak ada task overdue. 🎉</td>
                                </tr>
                                <tr v-for="task in overdueTasks" :key="task.id">
                                    <td class="px-6 py-4">
                                        <Link :href="route('tasks.show', task.id)" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">{{ task.title }}</Link>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ task.assignee || 'Belum ditentukan' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ task.unit || '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="['text-xs px-2 py-0.5 rounded-full', priorityColor(task.priority)]">{{ task.priority }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ task.deadline }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm" :class="severityColor(task.days_overdue)">{{ task.days_overdue }} hari</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
