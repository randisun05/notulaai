<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AnalyticsNav from '@/Pages/Analytics/Partials/AnalyticsNav.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    productivity: { type: Array, required: true },
});

const topEmployee = computed(() => props.productivity[0] || null);

const maxCompleted = computed(() => Math.max(1, ...props.productivity.map((p) => p.completed_count)));
</script>

<template>
    <Head title="Produktivitas Tim" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="page-heading">Produktivitas Tim</h2>
        </template>

        <div class="page-shell">
            <div class="page-container max-w-5xl">
                <AnalyticsNav />

                <div class="space-y-6">
                <div v-if="topEmployee" class="card-padded flex items-center gap-4">
                    <div class="h-14 w-14 rounded-full bg-[#eda100]/15 flex items-center justify-center text-2xl">🏆</div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Top Employee</p>
                        <p class="text-lg font-semibold text-gray-900">{{ topEmployee.name }}</p>
                        <p class="text-sm text-gray-500">{{ topEmployee.completed_count }} task selesai &middot; {{ topEmployee.completion_rate }}% completion rate</p>
                    </div>
                </div>

                <p v-if="productivity.length === 0" class="empty-state">Belum ada data task yang bisa dianalisis.</p>

                <div v-else class="table-wrap">
                    <table class="table-base">
                        <thead class="table-head">
                            <tr>
                                <th class="table-head-cell">Nama</th>
                                <th class="table-head-cell">Unit</th>
                                <th class="table-head-cell">Task Selesai</th>
                                <th class="table-head-cell">Overdue</th>
                                <th class="table-head-cell">Completion Rate</th>
                            </tr>
                        </thead>
                        <tbody class="table-body">
                            <tr v-for="(row, i) in productivity" :key="row.id" class="table-row-hover">
                                <td class="table-cell whitespace-nowrap font-medium text-gray-900">
                                    <span v-if="i === 0" class="mr-1">🏆</span>{{ row.name }}
                                </td>
                                <td class="table-cell whitespace-nowrap">{{ row.unit || '-' }}</td>
                                <td class="table-cell whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <div class="w-24 bg-gray-100 rounded-full h-2 overflow-hidden">
                                            <div class="h-full rounded-full bg-[#2a78d6]" :style="{ width: `${(row.completed_count / maxCompleted) * 100}%` }"></div>
                                        </div>
                                        <span>{{ row.completed_count }}</span>
                                    </div>
                                </td>
                                <td class="table-cell whitespace-nowrap">
                                    <span :class="row.overdue_count > 0 ? 'text-[#d03b3b] font-medium' : 'text-gray-400'">{{ row.overdue_count }}</span>
                                </td>
                                <td class="table-cell whitespace-nowrap">{{ row.completion_rate }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
