<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    DocumentTextIcon,
    CheckCircleIcon,
    CalendarDaysIcon,
    ClipboardDocumentCheckIcon,
    ExclamationTriangleIcon,
    ChartBarIcon,
} from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const props = defineProps({
    stats: { type: Object, required: true },
    recentMeetings: { type: Array, required: true },
    weeklyMeetings: { type: Array, default: () => [] },
    taskStatusBreakdown: { type: Array, default: () => [] },
});

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

const statCards = computed(() => [
    { name: 'Rapat Hari Ini', icon: CalendarDaysIcon, stat: props.stats.meetings_today, tone: 'default' },
    { name: 'Rapat Minggu Ini', icon: DocumentTextIcon, stat: props.stats.meetings_this_week, tone: 'default' },
    { name: 'Task Selesai', icon: CheckCircleIcon, stat: props.stats.tasks_completed, tone: 'good' },
    { name: 'Task Overdue', icon: ExclamationTriangleIcon, stat: props.stats.tasks_overdue, tone: props.stats.tasks_overdue > 0 ? 'critical' : 'default' },
    { name: 'Progress Task', icon: ClipboardDocumentCheckIcon, stat: `${props.stats.progress_percent}%`, tone: 'default' },
]);

const toneClasses = {
    default: 'text-gray-900',
    good: 'text-[#0ca30c]',
    critical: 'text-[#d03b3b]',
};

// --- Chart: Volume Rapat 8 Minggu Terakhir (bar tunggal, hue kategori slot-1) ---
const maxWeekly = computed(() => Math.max(1, ...props.weeklyMeetings.map((w) => w.count)));
const CHART_SERIES_BLUE = '#2a78d6';

// --- Chart: Distribusi Status Task (bar horizontal, urutan tetap sesuai alur status) ---
const STATUS_COLORS = {
    Todo: '#898781',
    'In Progress': '#2a78d6',
    Waiting: '#eda100',
    Review: '#4a3aa7',
    Done: '#0ca30c',
    Cancelled: '#c3c2b7',
};
const maxStatusCount = computed(() => Math.max(1, ...props.taskStatusBreakdown.map((s) => s.count)));
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

                <!-- Stat Tiles -->
                <div>
                    <h3 class="text-base font-semibold leading-6 text-gray-900">Ringkasan</h3>
                    <dl class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-5">
                        <div v-for="item in statCards" :key="item.name" class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
                            <dt class="truncate text-sm font-medium text-gray-500">
                                <component :is="item.icon" class="h-5 w-5 text-gray-400 inline-block mr-1.5 align-text-bottom" aria-hidden="true" />
                                {{ item.name }}
                            </dt>
                            <dd class="mt-1 text-3xl font-semibold tracking-tight" :class="toneClasses[item.tone]">{{ item.stat }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Charts -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <!-- Volume Rapat -->
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-1.5">
                            <ChartBarIcon class="h-4 w-4 text-gray-400" /> Volume Rapat (8 Minggu Terakhir)
                        </h3>
                        <div v-if="weeklyMeetings.every(w => w.count === 0)" class="text-sm text-gray-400 py-10 text-center">Belum ada data rapat.</div>
                        <div v-else class="flex items-end gap-2 h-40">
                            <div v-for="week in weeklyMeetings" :key="week.label" class="flex-1 flex flex-col items-center justify-end h-full group relative">
                                <span class="text-xs text-gray-500 mb-1 opacity-0 group-hover:opacity-100 transition-opacity">{{ week.count }}</span>
                                <div
                                    class="w-full rounded-t-sm transition-all"
                                    :style="{ height: `${Math.max(4, (week.count / maxWeekly) * 100)}%`, backgroundColor: CHART_SERIES_BLUE }"
                                    :title="`${week.label}: ${week.count} rapat`"
                                ></div>
                                <span class="text-[10px] text-gray-400 mt-1 whitespace-nowrap">{{ week.label }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Distribusi Status Task -->
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-1.5">
                            <ChartBarIcon class="h-4 w-4 text-gray-400" /> Distribusi Status Task
                        </h3>
                        <div v-if="taskStatusBreakdown.every(s => s.count === 0)" class="text-sm text-gray-400 py-10 text-center">Belum ada data task.</div>
                        <div v-else class="space-y-2.5">
                            <div v-for="s in taskStatusBreakdown" :key="s.status" class="flex items-center gap-2">
                                <span class="w-24 text-xs text-gray-500 shrink-0">{{ s.status }}</span>
                                <div class="flex-1 bg-gray-100 rounded-full h-2.5 overflow-hidden">
                                    <div
                                        class="h-full rounded-full"
                                        :style="{ width: `${(s.count / maxStatusCount) * 100}%`, backgroundColor: STATUS_COLORS[s.status] }"
                                    ></div>
                                </div>
                                <span class="w-6 text-xs text-gray-600 text-right shrink-0">{{ s.count }}</span>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Daftar Rapat Terbaru -->
                <div>
                     <h3 class="text-base font-semibold leading-6 text-gray-900 mb-5">Rapat Terbaru</h3>
                     <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Judul Rapat</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="relative px-6 py-3">
                                            <span class="sr-only">Aksi</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="meeting in recentMeetings" :key="meeting.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ meeting.title }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDate(meeting.date) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                  :class="{
                                                    'bg-blue-100 text-blue-800': meeting.status === 'Dijadwalkan',
                                                    'bg-yellow-100 text-yellow-800': meeting.status === 'Memproses',
                                                    'bg-green-100 text-green-800': meeting.status === 'Selesai Diproses',
                                                    'bg-red-100 text-red-800': meeting.status === 'Gagal'
                                                  }">
                                                {{ meeting.status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <Link :href="route('meetings.show', meeting.id)" class="text-indigo-600 hover:text-indigo-900">Lihat Detail</Link>
                                        </td>
                                    </tr>
                                    <tr v-if="recentMeetings.length === 0">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" colspan="4">
                                            Belum ada rapat yang dijadwalkan.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                     </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
