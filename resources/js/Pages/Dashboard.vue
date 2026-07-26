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
    SparklesIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    stats: { type: Object, required: true },
    recentMeetings: { type: Array, required: true },
    weeklyMeetings: { type: Array, default: () => [] },
    taskStatusBreakdown: { type: Array, default: () => [] },
});

const insight = ref(null);
const insightLoading = ref(false);
const insightError = ref('');

const generateInsight = async () => {
    insightLoading.value = true;
    insightError.value = '';

    try {
        const { data } = await axios.post(route('dashboard.insight'));
        insight.value = data.insight;
    } catch (e) {
        insightError.value = e.response?.data?.error || 'Gagal membuat insight.';
    } finally {
        insightLoading.value = false;
    }
};

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
            <h2 class="page-heading">Dashboard</h2>
        </template>

        <div class="page-shell">
            <div class="page-container">

                <!-- Stat Tiles -->
                <div>
                    <h3 class="text-base font-semibold leading-6 text-gray-900">Ringkasan</h3>
                    <dl class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-5">
                        <div v-for="item in statCards" :key="item.name" class="card-padded">
                            <dt class="truncate text-sm font-medium text-gray-500">
                                <component :is="item.icon" class="h-5 w-5 text-gray-400 inline-block mr-1.5 align-text-bottom" aria-hidden="true" />
                                {{ item.name }}
                            </dt>
                            <dd class="mt-1 text-3xl font-semibold tracking-tight" :class="toneClasses[item.tone]">{{ item.stat }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- AI Insight -->
                <div class="card-padded">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-1.5">
                            <SparklesIcon class="h-4 w-4 text-brand-500" /> AI Insight
                        </h3>
                        <button @click="generateInsight" :disabled="insightLoading" class="btn-primary btn-sm">
                            {{ insightLoading ? 'Membuat...' : (insight ? 'Buat Ulang' : 'Buat Insight') }}
                        </button>
                    </div>
                    <p v-if="insightError" class="mt-3 text-sm text-red-600">{{ insightError }}</p>
                    <p v-else-if="insight" class="mt-3 text-sm text-gray-700 whitespace-pre-wrap">{{ insight }}</p>
                    <p v-else class="mt-3 text-sm text-gray-400">Klik "Buat Insight" untuk mendapatkan ringkasan AI dari data di atas.</p>
                </div>

                <!-- Charts -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <!-- Volume Rapat -->
                    <div class="card-padded">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-1.5">
                            <ChartBarIcon class="h-4 w-4 text-gray-400" /> Volume Rapat (8 Minggu Terakhir)
                        </h3>
                        <div v-if="weeklyMeetings.every(w => w.count === 0)" class="empty-state">Belum ada data rapat.</div>
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
                    <div class="card-padded">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-1.5">
                            <ChartBarIcon class="h-4 w-4 text-gray-400" /> Distribusi Status Task
                        </h3>
                        <div v-if="taskStatusBreakdown.every(s => s.count === 0)" class="empty-state">Belum ada data task.</div>
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
                     <div class="table-wrap">
                        <table class="table-base">
                            <thead class="table-head">
                                <tr>
                                    <th scope="col" class="table-head-cell">Judul Rapat</th>
                                    <th scope="col" class="table-head-cell">Tanggal</th>
                                    <th scope="col" class="table-head-cell">Status</th>
                                    <th scope="col" class="relative px-6 py-3">
                                        <span class="sr-only">Aksi</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="table-body">
                                <tr v-for="meeting in recentMeetings" :key="meeting.id" class="table-row-hover">
                                    <td class="table-cell whitespace-nowrap font-medium text-gray-900">{{ meeting.title }}</td>
                                    <td class="table-cell whitespace-nowrap">{{ formatDate(meeting.date) }}</td>
                                    <td class="table-cell whitespace-nowrap">
                                        <span
                                            :class="{
                                                'badge-blue': meeting.status === 'Dijadwalkan',
                                                'badge-yellow': meeting.status === 'Memproses',
                                                'badge-green': meeting.status === 'Selesai Diproses',
                                                'badge-red': meeting.status === 'Gagal',
                                            }"
                                        >
                                            {{ meeting.status }}
                                        </span>
                                    </td>
                                    <td class="table-cell whitespace-nowrap text-right font-medium">
                                        <Link :href="route('meetings.show', meeting.id)" class="btn-link">Lihat Detail</Link>
                                    </td>
                                </tr>
                                <tr v-if="recentMeetings.length === 0">
                                    <td class="empty-state" colspan="4">
                                        Belum ada rapat yang dijadwalkan.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                     </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
