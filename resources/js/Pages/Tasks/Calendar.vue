<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    tasksByDate: { type: Object, required: true },
    month: { type: Number, required: true },
    year: { type: Number, required: true },
});

const MONTH_NAMES = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const WEEKDAYS = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

const pad = (n) => String(n).padStart(2, '0');
const dateKey = (y, m, d) => `${y}-${pad(m)}-${pad(d)}`;

const calendarWeeks = computed(() => {
    const firstDay = new Date(props.year, props.month - 1, 1);
    const daysInMonth = new Date(props.year, props.month, 0).getDate();
    const startOffset = firstDay.getDay();

    const cells = [];
    for (let i = 0; i < startOffset; i++) {
        cells.push(null);
    }
    for (let day = 1; day <= daysInMonth; day++) {
        cells.push({ day, key: dateKey(props.year, props.month, day) });
    }
    while (cells.length % 7 !== 0) {
        cells.push(null);
    }

    const weeks = [];
    for (let i = 0; i < cells.length; i += 7) {
        weeks.push(cells.slice(i, i + 7));
    }
    return weeks;
});

const isToday = (key) => {
    const now = new Date();
    return key === dateKey(now.getFullYear(), now.getMonth() + 1, now.getDate());
};

const goToMonth = (month, year) => {
    router.get(route('tasks.calendar'), { month, year }, { preserveState: true, replace: true });
};

const previousMonth = () => {
    const m = props.month === 1 ? 12 : props.month - 1;
    const y = props.month === 1 ? props.year - 1 : props.year;
    goToMonth(m, y);
};

const nextMonth = () => {
    const m = props.month === 12 ? 1 : props.month + 1;
    const y = props.month === 12 ? props.year + 1 : props.year;
    goToMonth(m, y);
};

const priorityColor = (priority) => ({
    Urgent: 'bg-red-100 text-red-700',
    High: 'bg-orange-100 text-orange-700',
    Medium: 'bg-brand-100 text-brand-700',
    Low: 'bg-gray-100 text-gray-600',
}[priority] || 'bg-gray-100 text-gray-600');
</script>

<template>
    <Head title="Kalender Task" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="page-heading">Kalender Task</h2>
                <Link :href="route('tasks.index')" class="btn-secondary">Daftar</Link>
            </div>
        </template>

        <div class="page-shell">
            <div class="page-container">
                <div class="card-padded">

                    <div class="flex items-center justify-between mb-4">
                        <button @click="previousMonth" class="btn-ghost">&larr; Sebelumnya</button>
                        <h3 class="text-base font-semibold text-gray-800">{{ MONTH_NAMES[month - 1] }} {{ year }}</h3>
                        <button @click="nextMonth" class="btn-ghost">Berikutnya &rarr;</button>
                    </div>

                    <div class="grid grid-cols-7 gap-px bg-gray-200 border border-gray-200 rounded-md overflow-hidden text-xs font-semibold text-gray-500 uppercase">
                        <div v-for="wd in WEEKDAYS" :key="wd" class="bg-gray-50 text-center py-2">{{ wd }}</div>
                    </div>

                    <div class="grid grid-cols-7 gap-px bg-gray-200 border-x border-b border-gray-200 rounded-b-md overflow-hidden">
                        <template v-for="(week, wi) in calendarWeeks" :key="wi">
                            <div
                                v-for="(cell, ci) in week"
                                :key="ci"
                                :class="['bg-white min-h-[6rem] p-1.5 align-top', !cell ? 'bg-gray-50' : '']"
                            >
                                <template v-if="cell">
                                    <div :class="['text-xs mb-1', isToday(cell.key) ? 'inline-flex items-center justify-center w-5 h-5 rounded-full bg-brand-600 text-white font-semibold' : 'text-gray-500']">{{ cell.day }}</div>
                                    <div class="space-y-1">
                                        <Link
                                            v-for="task in (tasksByDate[cell.key] || [])"
                                            :key="task.id"
                                            :href="route('tasks.show', task.id)"
                                            :class="['block text-xs px-1.5 py-0.5 rounded truncate', priorityColor(task.priority)]"
                                            :title="task.title"
                                        >
                                            {{ task.title }}
                                        </Link>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
