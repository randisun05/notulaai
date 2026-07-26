<script setup>
import { Link } from '@inertiajs/vue3';

const tabs = [
    { label: 'Produktivitas Tim', route: 'analytics.productivity' },
    { label: 'Heatmap Task', route: 'analytics.heatmap' },
];
</script>

<template>
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex space-x-6" aria-label="Tabs">
            <Link
                v-for="tab in tabs"
                :key="tab.route"
                :href="route(tab.route)"
                :class="['whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm', route().current(tab.route) ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']"
            >
                {{ tab.label }}
            </Link>
        </nav>
    </div>
</template>
