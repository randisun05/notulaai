<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AnalyticsNav from '@/Pages/Analytics/Partials/AnalyticsNav.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    weeks: { type: Array, required: true },
    maxCount: { type: Number, required: true },
});

const WEEKDAY_LABELS = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

const cellColor = (day) => {
    if (!day || day.count === 0) return '#ebedf0';
    if (props.maxCount === 0) return '#ebedf0';

    const ratio = day.count / props.maxCount;
    if (ratio <= 0.25) return '#b7d3f6';
    if (ratio <= 0.5) return '#6da7ec';
    if (ratio <= 0.75) return '#2a78d6';
    return '#104281';
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};
</script>

<template>
    <Head title="Heatmap Task" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="page-heading">Heatmap Task</h2>
        </template>

        <div class="page-shell">
            <div class="page-container max-w-5xl">
                <AnalyticsNav />

                <div class="card-padded">
                    <p class="text-sm text-gray-500 mb-4">Jumlah Task yang dibuat per hari, 12 minggu terakhir.</p>

                    <div class="overflow-x-auto">
                        <div class="flex gap-1 w-max">
                            <div class="flex flex-col gap-1 justify-around pr-1">
                                <span v-for="wd in WEEKDAY_LABELS" :key="wd" class="text-[10px] text-gray-400 h-3.5 leading-[0.875rem]">{{ wd }}</span>
                            </div>
                            <div v-for="(week, wi) in weeks" :key="wi" class="flex flex-col gap-1">
                                <div
                                    v-for="(day, di) in week"
                                    :key="di"
                                    class="h-3.5 w-3.5 rounded-sm"
                                    :style="{ backgroundColor: cellColor(day) }"
                                    :title="day ? `${formatDate(day.date)}: ${day.count} task dibuat` : ''"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 mt-4 text-xs text-gray-400">
                        <span>Sedikit</span>
                        <span class="h-3.5 w-3.5 rounded-sm" style="background-color: #ebedf0"></span>
                        <span class="h-3.5 w-3.5 rounded-sm" style="background-color: #b7d3f6"></span>
                        <span class="h-3.5 w-3.5 rounded-sm" style="background-color: #6da7ec"></span>
                        <span class="h-3.5 w-3.5 rounded-sm" style="background-color: #2a78d6"></span>
                        <span class="h-3.5 w-3.5 rounded-sm" style="background-color: #104281"></span>
                        <span>Banyak</span>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
