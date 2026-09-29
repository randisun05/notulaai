<script setup>
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import FollowUpBadge from '@/Pages/Meetings/Partials/FollowUpBadge.vue';

// Rapat berseri: rapat sebelumnya/lanjutan, tindak lanjut yang belum selesai, dan
// keputusan dari rapat-rapat sebelumnya (MeetingSeriesService::memory()).
defineProps({
    meeting: { type: Object, required: true },
    series: { type: Object, default: null },
    canUpdate: { type: Boolean, default: false },
});

const showDecisions = ref(false);
const date = (d) => (d ? new Date(String(d).replace(' ', 'T')).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '');
</script>

<template>
    <div class="card-padded">
        <div class="flex items-center justify-between gap-2">
            <h3 class="text-lg font-semibold text-gray-900">Seri Rapat</h3>
            <Link v-if="canUpdate" :href="route('meetings.create', { previous: meeting.id })" class="text-sm text-brand-600 hover:text-brand-800">+ Rapat lanjutan</Link>
        </div>

        <p v-if="!series" class="mt-2 text-sm text-gray-500">
            Rapat ini belum terhubung ke rapat lain. Jadwalkan rapat lanjutan supaya tindak lanjut &amp; keputusannya terbawa.
        </p>

        <template v-else>
            <div v-if="series.previous.length" class="mt-3 text-sm">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Rapat sebelumnya</div>
                <ol class="mt-1 space-y-1">
                    <li v-for="m in series.previous" :key="m.id">
                        <Link :href="route('meetings.show', m.id)" class="text-brand-600 hover:text-brand-800">{{ m.title }}</Link>
                        <span class="text-xs text-gray-400"> · {{ date(m.date) }}</span>
                    </li>
                </ol>
            </div>
            <div v-if="series.next.length" class="mt-3 text-sm">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Rapat lanjutan</div>
                <ul class="mt-1 space-y-1">
                    <li v-for="m in series.next" :key="m.id">
                        <Link :href="route('meetings.show', m.id)" class="text-brand-600 hover:text-brand-800">{{ m.title }}</Link>
                        <span class="text-xs text-gray-400"> · {{ date(m.date) }}</span>
                    </li>
                </ul>
            </div>

            <div v-if="series.previous.length" class="mt-4 border-t border-gray-100 pt-3">
                <div class="text-sm font-semibold text-gray-800">Tindak lanjut belum selesai ({{ series.open_follow_ups.length }})</div>
                <p v-if="!series.open_follow_ups.length" class="mt-1 text-sm text-green-700">Semua tindak lanjut rapat sebelumnya sudah selesai.</p>
                <ul v-else class="mt-2 space-y-2 text-sm">
                    <li v-for="item in series.open_follow_ups" :key="item.id">
                        <component :is="item.task_id ? Link : 'span'" :href="item.task_id ? route('tasks.show', item.task_id) : undefined" :class="item.task_id ? 'text-gray-900 hover:text-brand-700' : 'text-gray-900'">{{ item.title }}</component>
                        <div class="text-xs text-gray-500">
                            <span :class="item.overdue ? 'text-red-600 font-medium' : ''">{{ item.status }}</span>
                            <span v-if="item.assignee"> · {{ item.assignee }}</span>
                            <span v-if="item.deadline" :class="item.overdue ? 'text-red-600' : ''"> · tenggat {{ date(item.deadline) }}</span>
                            <span> · {{ date(item.meeting.date) }}</span>
                        </div>
                    </li>
                </ul>
            </div>

            <div v-if="series.decisions.length" class="mt-4 border-t border-gray-100 pt-3">
                <button type="button" class="text-sm font-semibold text-gray-800" @click="showDecisions = !showDecisions">
                    {{ showDecisions ? '▾' : '▸' }} Keputusan rapat sebelumnya ({{ series.decisions.length }})
                </button>
                <ul v-if="showDecisions" class="mt-2 space-y-2 text-sm">
                    <li v-for="d in series.decisions" :key="d.id">
                        <div class="text-gray-800">{{ d.text }}</div>
                        <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                            <FollowUpBadge :follow-up="d.follow_up" />
                            <span>{{ date(d.meeting.date) }}</span>
                        </div>
                    </li>
                </ul>
            </div>
        </template>
    </div>
</template>
