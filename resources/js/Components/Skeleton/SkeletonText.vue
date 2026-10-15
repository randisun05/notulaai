<script setup>
import SkeletonBlock from '@/Components/Skeleton/SkeletonBlock.vue';

// A paragraph of placeholder lines; the last one is shorter like real text.
defineProps({
    lines: { type: Number, default: 3 },
    label: { type: String, default: 'Memuat...' },
});
</script>

<template>
    <div role="status" aria-busy="true" class="space-y-2.5">
        <span class="sr-only">{{ label }}</span>
        <SkeletonBlock
            v-for="n in lines"
            :key="n"
            class="h-3.5"
            :class="n === lines && lines > 1 ? 'w-2/3' : 'w-full'"
        />
    </div>
</template>
