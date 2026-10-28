<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    days: { type: Number, required: true },
    periods: { type: Array, required: true },
    totals: { type: Object, required: true },
    daily: { type: Array, required: true },
    byProvider: { type: Array, required: true },
    byType: { type: Array, required: true },
    byUnit: { type: Array, required: true },
    recentFailures: { type: Array, required: true },
});

const TYPE_LABELS = {
    text: 'Rangkuman',
    action_items: 'Action items',
    transcription: 'Transkripsi',
    ocr: 'OCR gambar',
    chat: 'Tanya AI',
    email_draft: 'Draft email',
    dashboard_insight: 'Insight dashboard',
};

const nf = new Intl.NumberFormat('id-ID');
const formatNumber = (n) => nf.format(n ?? 0);

// Cost is a lower bound when some successful calls had no known price.
const formatCost = (cost, unpriced = 0) => {
    if (cost === null || cost === undefined) return unpriced > 0 ? 'Tidak diketahui' : '$0';
    const value = cost < 0.01 && cost > 0 ? `$${cost.toFixed(4)}` : `$${cost.toFixed(2)}`;
    return unpriced > 0 ? `≥ ${value}` : value;
};

const failureRate = (row) => (row.requests ? Math.round((row.failed / row.requests) * 1000) / 10 : 0);

const formatDay = (day) => new Date(`${day}T00:00:00`).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
const formatDateTime = (iso) => new Date(iso).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

const setPeriod = (days) => {
    router.get(route('admin.ai-usage.index'), { days }, { preserveState: true, preserveScroll: true, replace: true });
};

const statCards = computed(() => [
    { name: 'Total request', value: formatNumber(props.totals.requests) },
    {
        name: 'Gagal',
        value: `${failureRate(props.totals)}%`,
        hint: `${formatNumber(props.totals.failed)} request`,
        tone: failureRate(props.totals) >= 10 ? 'critical' : null,
    },
    { name: 'Token', value: formatNumber(props.totals.tokens), hint: props.totals.avg_duration_ms ? `rata-rata ${formatNumber(props.totals.avg_duration_ms)} ms` : null },
    {
        name: 'Biaya',
        value: formatCost(props.totals.cost, props.totals.unpriced),
        hint: props.totals.unpriced ? `${formatNumber(props.totals.unpriced)} request tanpa harga` : null,
    },
]);

// --- Daily requests: single-series bar chart (slot-1 blue, same token as Dashboard) ---
const CHART_SERIES_BLUE = '#2a78d6';
const maxDaily = computed(() => Math.max(1, ...props.daily.map((d) => d.requests)));
const hoveredDay = ref(null);
// Label roughly 6 ticks so the axis never crowds, whatever the period.
const tickEvery = computed(() => Math.max(1, Math.ceil(props.daily.length / 6)));
const showTable = ref(false);
</script>

<template>
    <Head title="Pemakaian AI" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="page-heading">Pemakaian & Biaya AI</h2>
                    <p class="page-subheading">Semua panggilan AI: rangkuman, action items, transkripsi, OCR, chat, email, dan insight.</p>
                </div>
                <div class="inline-flex rounded-lg border border-gray-200 bg-white p-0.5" role="group" aria-label="Periode">
                    <button
                        v-for="p in periods"
                        :key="p"
                        type="button"
                        @click="setPeriod(p)"
                        :aria-pressed="p === days"
                        :class="['px-3 py-1.5 text-sm font-medium rounded-md transition', p === days ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-50']"
                    >
                        {{ p }} hari
                    </button>
                </div>
            </div>
        </template>

        <div class="page-shell">
            <div class="page-container">
                <dl class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div v-for="card in statCards" :key="card.name" class="card-padded">
                        <dt class="text-sm font-medium text-gray-500">{{ card.name }}</dt>
                        <dd class="mt-1 text-3xl font-semibold tracking-tight" :class="card.tone === 'critical' ? 'text-[#d03b3b]' : 'text-gray-900'">{{ card.value }}</dd>
                        <dd v-if="card.hint" class="mt-1 text-xs text-gray-500">{{ card.hint }}</dd>
                    </div>
                </dl>

                <!-- Daily volume -->
                <div class="card-padded">
                    <div class="card-header">
                        <h3 class="text-sm font-semibold text-gray-700">Request per hari</h3>
                        <button type="button" class="btn-link text-xs" @click="showTable = !showTable">
                            {{ showTable ? 'Tampilkan grafik' : 'Tampilkan tabel' }}
                        </button>
                    </div>

                    <div v-if="totals.requests === 0" class="empty-state">Belum ada panggilan AI pada periode ini.</div>

                    <div v-else-if="!showTable">
                        <div class="relative">
                            <div class="flex items-end gap-0.5 h-48 border-b border-gray-200" role="img" :aria-label="`Grafik request AI per hari, ${days} hari terakhir`">
                                <div
                                    v-for="(d, i) in daily"
                                    :key="d.day"
                                    class="flex-1 h-full flex items-end cursor-default"
                                    @mouseenter="hoveredDay = i"
                                    @mouseleave="hoveredDay = null"
                                >
                                    <div
                                        class="w-full rounded-t transition-opacity"
                                        :class="hoveredDay !== null && hoveredDay !== i ? 'opacity-50' : ''"
                                        :style="{ height: d.requests ? `${Math.max(2, (d.requests / maxDaily) * 100)}%` : '0', backgroundColor: CHART_SERIES_BLUE }"
                                    ></div>
                                </div>
                            </div>
                            <div
                                v-if="hoveredDay !== null"
                                class="absolute -top-2 pointer-events-none z-10 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs shadow-card whitespace-nowrap"
                                :style="{ left: `${((hoveredDay + 0.5) / daily.length) * 100}%`, transform: 'translate(-50%, -100%)' }"
                            >
                                <p class="font-semibold text-gray-900">{{ formatDay(daily[hoveredDay].day) }}</p>
                                <p class="text-gray-600">{{ formatNumber(daily[hoveredDay].requests) }} request · {{ formatNumber(daily[hoveredDay].failed) }} gagal</p>
                                <p class="text-gray-600">{{ formatNumber(daily[hoveredDay].tokens) }} token · {{ formatCost(daily[hoveredDay].cost, daily[hoveredDay].unpriced) }}</p>
                            </div>
                        </div>
                        <div class="flex gap-0.5 mt-2 text-[11px] text-gray-500">
                            <span v-for="(d, i) in daily" :key="d.day" class="flex-1 text-center whitespace-nowrap overflow-visible">
                                {{ i % tickEvery === 0 ? formatDay(d.day) : '' }}
                            </span>
                        </div>
                    </div>

                    <div v-else class="table-wrap max-h-80 overflow-y-auto">
                        <table class="table-base">
                            <thead class="table-head">
                                <tr>
                                    <th class="table-head-cell">Tanggal</th>
                                    <th class="table-head-cell text-right">Request</th>
                                    <th class="table-head-cell text-right">Gagal</th>
                                    <th class="table-head-cell text-right">Token</th>
                                    <th class="table-head-cell text-right">Biaya</th>
                                </tr>
                            </thead>
                            <tbody class="table-body">
                                <tr v-for="d in [...daily].reverse()" :key="d.day">
                                    <td class="table-cell">{{ formatDay(d.day) }}</td>
                                    <td class="table-cell text-right tabular-nums">{{ formatNumber(d.requests) }}</td>
                                    <td class="table-cell text-right tabular-nums">{{ formatNumber(d.failed) }}</td>
                                    <td class="table-cell text-right tabular-nums">{{ formatNumber(d.tokens) }}</td>
                                    <td class="table-cell text-right tabular-nums">{{ formatCost(d.cost, d.unpriced) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Breakdowns -->
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    <div class="card-padded">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4">Per provider & model</h3>
                        <div class="table-wrap">
                            <table class="table-base">
                                <thead class="table-head">
                                    <tr>
                                        <th class="table-head-cell">Provider</th>
                                        <th class="table-head-cell text-right">Request</th>
                                        <th class="table-head-cell text-right">Gagal</th>
                                        <th class="table-head-cell text-right">Biaya</th>
                                    </tr>
                                </thead>
                                <tbody class="table-body">
                                    <tr v-if="byProvider.length === 0"><td colspan="4" class="empty-state">Belum ada data.</td></tr>
                                    <tr v-for="row in byProvider" :key="`${row.provider}-${row.model}`">
                                        <td class="table-cell">
                                            <p class="font-medium text-gray-900">{{ row.provider }}</p>
                                            <p class="text-xs text-gray-500 font-mono">{{ row.model || '—' }}</p>
                                        </td>
                                        <td class="table-cell text-right tabular-nums">{{ formatNumber(row.requests) }}</td>
                                        <td class="table-cell text-right tabular-nums">{{ failureRate(row) }}%</td>
                                        <td class="table-cell text-right tabular-nums">{{ formatCost(row.cost, row.unpriced) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card-padded">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4">Per unit</h3>
                        <div class="table-wrap">
                            <table class="table-base">
                                <thead class="table-head">
                                    <tr>
                                        <th class="table-head-cell">Unit</th>
                                        <th class="table-head-cell text-right">Request</th>
                                        <th class="table-head-cell text-right">Token</th>
                                        <th class="table-head-cell text-right">Biaya</th>
                                    </tr>
                                </thead>
                                <tbody class="table-body">
                                    <tr v-if="byUnit.length === 0"><td colspan="4" class="empty-state">Belum ada data.</td></tr>
                                    <tr v-for="row in byUnit" :key="row.unit_id ?? 'none'">
                                        <td class="table-cell font-medium text-gray-900">{{ row.unit_name || 'Tanpa unit' }}</td>
                                        <td class="table-cell text-right tabular-nums">{{ formatNumber(row.requests) }}</td>
                                        <td class="table-cell text-right tabular-nums">{{ formatNumber(row.tokens) }}</td>
                                        <td class="table-cell text-right tabular-nums">{{ formatCost(row.cost, row.unpriced) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card-padded">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4">Per jenis</h3>
                        <div class="table-wrap">
                            <table class="table-base">
                                <thead class="table-head">
                                    <tr>
                                        <th class="table-head-cell">Jenis</th>
                                        <th class="table-head-cell text-right">Request</th>
                                        <th class="table-head-cell text-right">Gagal</th>
                                        <th class="table-head-cell text-right">Token</th>
                                    </tr>
                                </thead>
                                <tbody class="table-body">
                                    <tr v-if="byType.length === 0"><td colspan="4" class="empty-state">Belum ada data.</td></tr>
                                    <tr v-for="row in byType" :key="row.type">
                                        <td class="table-cell font-medium text-gray-900">{{ TYPE_LABELS[row.type] || row.type }}</td>
                                        <td class="table-cell text-right tabular-nums">{{ formatNumber(row.requests) }}</td>
                                        <td class="table-cell text-right tabular-nums">{{ failureRate(row) }}%</td>
                                        <td class="table-cell text-right tabular-nums">{{ formatNumber(row.tokens) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card-padded">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4">Kegagalan terakhir</h3>
                        <p v-if="recentFailures.length === 0" class="empty-state">Tidak ada kegagalan pada periode ini.</p>
                        <ul v-else class="divide-y divide-gray-100">
                            <li v-for="f in recentFailures" :key="f.id" class="py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="badge-red">{{ TYPE_LABELS[f.type] || f.type }} · {{ f.provider }}</span>
                                    <span class="text-xs text-gray-500 whitespace-nowrap">{{ formatDateTime(f.created_at) }}</span>
                                </div>
                                <p class="mt-1.5 text-sm text-gray-700 break-words line-clamp-2" :title="f.error_message">{{ f.error_message || 'Tanpa pesan error' }}</p>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ f.user?.name || 'Sistem' }}<span v-if="f.meeting"> · {{ f.meeting.title }}</span>
                                </p>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
