<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Produktivitas Tim</h2>
        </template>

        <div class="py-12">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <div v-if="topEmployee" class="bg-white overflow-hidden shadow-sm rounded-lg p-6 flex items-center gap-4">
                    <div class="h-14 w-14 rounded-full bg-[#eda100]/15 flex items-center justify-center text-2xl">🏆</div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Top Employee</p>
                        <p class="text-lg font-semibold text-gray-900">{{ topEmployee.name }}</p>
                        <p class="text-sm text-gray-500">{{ topEmployee.completed_count }} task selesai &middot; {{ topEmployee.completion_rate }}% completion rate</p>
                    </div>
                </div>

                <p v-if="productivity.length === 0" class="text-sm text-gray-500 text-center py-10">Belum ada data task yang bisa dianalisis.</p>

                <div v-else class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task Selesai</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Overdue</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completion Rate</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(row, i) in productivity" :key="row.id">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <span v-if="i === 0" class="mr-1">🏆</span>{{ row.name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ row.unit || '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                        <div class="flex items-center gap-2">
                                            <div class="w-24 bg-gray-100 rounded-full h-2 overflow-hidden">
                                                <div class="h-full rounded-full bg-[#2a78d6]" :style="{ width: `${(row.completed_count / maxCompleted) * 100}%` }"></div>
                                            </div>
                                            <span>{{ row.completed_count }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span :class="row.overdue_count > 0 ? 'text-[#d03b3b] font-medium' : 'text-gray-400'">{{ row.overdue_count }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ row.completion_rate }}%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
