<script setup>
import { Link } from '@inertiajs/vue3';

// Status tindak lanjut sebuah keputusan (MeetingDecision::follow_up).
defineProps({ followUp: { type: Object, required: true } });

const tone = {
    selesai: 'badge-green',
    berjalan: 'badge-blue',
    belum: 'badge-orange',
    tanpa: 'badge-gray',
};
</script>

<template>
    <Link v-if="followUp.task_id" :href="route('tasks.show', followUp.task_id)" :class="tone[followUp.status]" :title="followUp.title">
        {{ followUp.label }}<template v-if="followUp.status === 'berjalan'"> ({{ followUp.task_status }})</template>
    </Link>
    <span v-else :class="tone[followUp.status]" :title="followUp.title">{{ followUp.label }}</span>
</template>
