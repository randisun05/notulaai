<script setup>
defineProps({
    meeting: {
        type: Object,
        required: true,
    },
});

const ICONS = {
    'meeting.created': '🗓️',
    'meeting.processing_started': '⚙️',
    'meeting.processed': '✅',
    'meeting.failed': '⚠️',
    'comment.created': '💬',
    'comment.replied': '↩️',
    'task.created': '📌',
    'task.status_changed': '🔄',
    'task.disposed': '📤',
    'task.approval_requested': '🙋',
    'task.approved': '✅',
    'task.rejected': '❌',
    'email.sent': '✉️',
};

const iconFor = (type) => ICONS[type] || '•';

const formatDateTime = (value) => {
    return new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
};
</script>

<template>
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
        <h3 class="text-lg font-semibold mb-4 text-gray-900">Aktivitas</h3>

        <p v-if="!meeting.activities?.length" class="text-sm text-gray-500">Belum ada aktivitas tercatat.</p>

        <ol v-else class="space-y-4">
            <li v-for="activity in meeting.activities" :key="activity.id" class="flex gap-3">
                <span class="text-lg leading-none">{{ iconFor(activity.type) }}</span>
                <div>
                    <p class="text-sm text-gray-700">{{ activity.description }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ formatDateTime(activity.created_at) }}</p>
                </div>
            </li>
        </ol>
    </div>
</template>
