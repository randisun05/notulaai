<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FollowUpBadge from '@/Pages/Meetings/Partials/FollowUpBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    days: { type: Number, required: true },
    periods: { type: Object, required: true },
    units: { type: Array, required: true },
    totals: { type: Object, required: true },
    overdue: { type: Array, default: () => [] },
    recentDecisions: { type: Array, default: () => [] },
    pendingMinutes: { type: Array, default: () => [] },
});

const setPeriod = (days) => router.get(route('leadership.index'), { days }, { preserveScroll: true });

const cards = computed(() => [
    { label: 'Rapat', value: props.totals.meetings, hint: `${props.totals.processed} sudah bernotula` },
    { label: 'Notulen disahkan', value: props.totals.minutes_approved },
    { label: 'Keputusan', value: props.totals.decisions },
    { label: 'Tindak lanjut terbuka', value: props.totals.open_tasks, hint: 'semua waktu' },
    { label: 'Terlambat', value: props.totals.overdue_tasks, critical: props.totals.overdue_tasks > 0 },
    { label: 'Penyelesaian', value: props.totals.completion_rate === null ? '—' : `${props.totals.completion_rate}%`, hint: 'Task dibuat dalam periode' },
]);

const rateClass = (rate) => (rate === null ? 'text-gray-400' : rate >= 75 ? 'text-green-700' : rate >= 50 ? 'text-amber-700' : 'text-red-700');
const date = (d) => (d ? new Date(String(d).replace(' ', 'T')).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '');
</script>

<template>
    <Head title="Dasbor Pimpinan" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="page-heading">Dasbor Pimpinan</h2>
                    <p class="page-subheading">Rapat, keputusan, dan tindak lanjut seluruh unit.</p>
                </div>
                <div class="inline-flex rounded-lg border border-gray-200 bg-white p-0.5 text-sm">
                    <button v-for="(label, value) in periods" :key="value" type="button"
                        :class="['rounded-md px-3 py-1.5', Number(value) === days ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-50']"
                        @click="setPeriod(value)">{{ label }}</button>
                </div>
            </div>
        </template>

        <div class="page-shell">
            <div class="page-container">
                <dl class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
                    <div v-for="card in cards" :key="card.label" class="card-padded">
                        <dt class="text-xs font-medium text-gray-500">{{ card.label }}</dt>
                        <dd class="mt-1 text-2xl font-semibold" :class="card.critical ? 'text-red-600' : 'text-gray-900'">{{ card.value }}</dd>
                        <dd v-if="card.hint" class="text-xs text-gray-400">{{ card.hint }}</dd>
                    </div>
                </dl>

                <div class="card mt-6 overflow-x-auto">
                    <table class="table-base min-w-full">
                        <thead class="table-head">
                            <tr>
                                <th class="table-head-cell">Unit</th>
                                <th class="table-head-cell text-right">Rapat</th>
                                <th class="table-head-cell text-right">Bernotula</th>
                                <th class="table-head-cell text-right">Notulen disahkan</th>
                                <th class="table-head-cell text-right">Keputusan</th>
                                <th class="table-head-cell text-right">TL terbuka</th>
                                <th class="table-head-cell text-right">Terlambat</th>
                                <th class="table-head-cell text-right">Penyelesaian</th>
                            </tr>
                        </thead>
                        <tbody class="table-body">
                            <tr v-for="unit in units" :key="unit.id" class="table-row-hover">
                                <td class="table-cell font-medium text-gray-900">{{ unit.name }}</td>
                                <td class="table-cell text-right">{{ unit.meetings }}</td>
                                <td class="table-cell text-right">{{ unit.processed }}</td>
                                <td class="table-cell text-right">{{ unit.minutes_approved }}</td>
                                <td class="table-cell text-right">
                                    <Link v-if="unit.decisions" :href="route('decisions.index', { unit_id: unit.id })" class="text-brand-600 hover:text-brand-800">{{ unit.decisions }}</Link>
                                    <span v-else>0</span>
                                </td>
                                <td class="table-cell text-right">{{ unit.open_tasks }}</td>
                                <td class="table-cell text-right" :class="unit.overdue_tasks ? 'text-red-600 font-semibold' : ''">{{ unit.overdue_tasks }}</td>
                                <td class="table-cell text-right font-medium" :class="rateClass(unit.completion_rate)">
                                    {{ unit.completion_rate === null ? '—' : unit.completion_rate + '%' }}
                                </td>
                            </tr>
                            <tr v-if="!units.length"><td colspan="8" class="table-cell empty-state">Belum ada unit.</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div class="card-padded">
                        <h3 class="card-title">Tindak lanjut paling terlambat</h3>
                        <p v-if="!overdue.length" class="empty-state">Tidak ada tindak lanjut yang terlambat.</p>
                        <ul v-else class="mt-3 divide-y divide-gray-100 text-sm">
                            <li v-for="task in overdue" :key="task.id" class="py-2">
                                <Link :href="route('tasks.show', task.id)" class="font-medium text-gray-900 hover:text-brand-700">{{ task.title }}</Link>
                                <div class="text-xs text-gray-500">
                                    <span class="font-semibold text-red-600">{{ task.days_overdue }} hari</span>
                                    · {{ task.unit || '—' }} · {{ task.assignee || 'tanpa PIC' }} · {{ task.status }}
                                    <template v-if="task.meeting"> · <Link :href="route('meetings.show', task.meeting.id)" class="text-brand-600">{{ task.meeting.title }}</Link></template>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="card-padded">
                        <h3 class="card-title">Notulen menunggu pengesahan</h3>
                        <p v-if="!pendingMinutes.length" class="empty-state">Tidak ada notulen yang menunggu.</p>
                        <ul v-else class="mt-3 divide-y divide-gray-100 text-sm">
                            <li v-for="item in pendingMinutes" :key="item.meeting.id" class="py-2">
                                <Link :href="route('meetings.minutes.edit', item.meeting.id)" class="font-medium text-gray-900 hover:text-brand-700">{{ item.meeting.title }}</Link>
                                <div class="text-xs text-gray-500">{{ item.unit }} · rapat {{ date(item.meeting.date) }} · diajukan {{ date(item.submitted_at) }}<template v-if="item.chairperson"> · pengesah: {{ item.chairperson }}</template></div>
                            </li>
                        </ul>
                    </div>

                    <div class="card-padded lg:col-span-2">
                        <div class="flex items-center justify-between">
                            <h3 class="card-title">Keputusan terbaru</h3>
                            <Link :href="route('decisions.index')" class="text-sm text-brand-600 hover:text-brand-800">Semua keputusan &rarr;</Link>
                        </div>
                        <p v-if="!recentDecisions.length" class="empty-state">Belum ada keputusan tercatat.</p>
                        <ul v-else class="mt-3 divide-y divide-gray-100 text-sm">
                            <li v-for="d in recentDecisions" :key="d.id" class="py-2">
                                <div class="text-gray-900">{{ d.text }}</div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                                    <FollowUpBadge :follow-up="d.follow_up" />
                                    <Link :href="route('meetings.show', d.meeting.id)" class="text-brand-600">{{ d.meeting.title }}</Link>
                                    <span>{{ date(d.meeting.date) }}</span>
                                    <span v-if="d.meeting.unit">{{ d.meeting.unit.name }}</span>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
