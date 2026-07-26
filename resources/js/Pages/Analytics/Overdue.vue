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
            <div class="flex justify-between items-center">
                <h2 class="page-heading">Overdue Analysis</h2>
                <a :href="route('tasks.export.pdf') + '?overdue=1'" class="btn-secondary">
                    Export PDF
                </a>
            </div>
        </template>

        <div class="page-shell">
            <div class="page-container max-w-5xl">
                <AnalyticsNav />

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Distribusi umur overdue -->
                    <div class="card-padded">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4">Distribusi Usia Overdue</h3>
                        <div v-if="overdueTasks.length === 0" class="empty-state py-6">Tidak ada task overdue. 🎉</div>
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
                    <div v-if="isSuperadmin" class="card-padded">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4">Overdue per Unit</h3>
                        <div v-if="byUnit.length === 0" class="empty-state py-6">Tidak ada data.</div>
                        <ul v-else class="space-y-2">
                            <li v-for="u in byUnit" :key="u.unit" class="flex items-center justify-between text-sm">
                                <span class="text-gray-600">{{ u.unit }}</span>
                                <span class="font-medium text-gray-900">{{ u.count }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="table-base">
                        <thead class="table-head">
                            <tr>
                                <th class="table-head-cell">Task</th>
                                <th class="table-head-cell">PIC</th>
                                <th class="table-head-cell">Unit</th>
                                <th class="table-head-cell">Prioritas</th>
                                <th class="table-head-cell">Deadline</th>
                                <th class="table-head-cell">Terlambat</th>
                            </tr>
                        </thead>
                        <tbody class="table-body">
                            <tr v-if="overdueTasks.length === 0">
                                <td colspan="6" class="empty-state">Tidak ada task overdue. 🎉</td>
                            </tr>
                            <tr v-for="task in overdueTasks" :key="task.id" class="table-row-hover">
                                <td class="table-cell">
                                    <Link :href="route('tasks.show', task.id)" class="font-medium text-brand-600 hover:text-brand-800">{{ task.title }}</Link>
                                </td>
                                <td class="table-cell whitespace-nowrap">{{ task.assignee || 'Belum ditentukan' }}</td>
                                <td class="table-cell whitespace-nowrap">{{ task.unit || '-' }}</td>
                                <td class="table-cell whitespace-nowrap">
                                    <span :class="['text-xs px-2 py-0.5 rounded-full', priorityColor(task.priority)]">{{ task.priority }}</span>
                                </td>
                                <td class="table-cell whitespace-nowrap">{{ task.deadline }}</td>
                                <td class="table-cell whitespace-nowrap" :class="severityColor(task.days_overdue)">{{ task.days_overdue }} hari</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
