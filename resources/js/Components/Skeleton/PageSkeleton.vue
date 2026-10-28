<script setup>
import SkeletonBlock from '@/Components/Skeleton/SkeletonBlock.vue';
import SkeletonText from '@/Components/Skeleton/SkeletonText.vue';

// Full-page placeholder shown by AuthenticatedLayout while an Inertia page
// visit is in flight. The variant mirrors the shape of the destination page
// (see resolveSkeletonVariant in Composables/useNavigationSkeleton.js).
defineProps({
    variant: {
        type: String,
        default: 'list',
        validator: (v) => ['dashboard', 'list', 'detail', 'form', 'board'].includes(v),
    },
});
</script>

<template>
    <div role="status" aria-busy="true" aria-live="polite">
        <span class="sr-only">Memuat halaman...</span>

        <header class="bg-white border-b border-gray-100">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 flex items-center justify-between gap-4">
                <div class="space-y-2 w-full max-w-xs">
                    <SkeletonBlock class="h-6 w-2/3" />
                    <SkeletonBlock class="h-3.5 w-full" />
                </div>
                <SkeletonBlock v-if="variant !== 'detail'" class="h-10 w-32 rounded-lg hidden sm:block" />
            </div>
        </header>

        <div class="page-shell">
            <div class="page-container">
                <!-- Dashboard: stat tiles, insight card, two charts -->
                <template v-if="variant === 'dashboard'">
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <div v-for="n in 4" :key="n" class="card-padded space-y-3">
                            <SkeletonBlock class="h-3.5 w-1/2" />
                            <SkeletonBlock class="h-8 w-1/3" />
                        </div>
                    </div>
                    <div class="card-padded space-y-4">
                        <SkeletonBlock class="h-4 w-28" />
                        <SkeletonText :lines="2" />
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div v-for="n in 2" :key="n" class="card-padded space-y-4">
                            <SkeletonBlock class="h-4 w-1/3" />
                            <div class="flex items-end gap-2 h-40">
                                <SkeletonBlock
                                    v-for="(h, i) in [40, 65, 30, 80, 55, 70, 45, 90]"
                                    :key="i"
                                    class="flex-1 rounded-b-none"
                                    :style="{ height: `${h}%` }"
                                />
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Detail (meeting / task): main tabbed card + sidebar -->
                <template v-else-if="variant === 'detail'">
                    <div class="flex gap-6">
                        <SkeletonBlock class="h-4 w-40" />
                        <SkeletonBlock class="h-4 w-28 rounded-full" />
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2 card-padded space-y-6">
                            <div class="flex gap-6 border-b border-gray-100 pb-3">
                                <SkeletonBlock v-for="n in 4" :key="n" class="h-4 w-20" />
                            </div>
                            <SkeletonBlock class="h-5 w-1/3" />
                            <SkeletonText :lines="6" />
                        </div>
                        <div class="space-y-6">
                            <div v-for="n in 2" :key="n" class="card-padded space-y-4">
                                <SkeletonBlock class="h-5 w-1/2" />
                                <SkeletonText :lines="3" />
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Form (create / edit / settings / profile) -->
                <template v-else-if="variant === 'form'">
                    <div class="card-padded max-w-3xl space-y-6">
                        <div v-for="n in 4" :key="n" class="space-y-2">
                            <SkeletonBlock class="h-3.5 w-24" />
                            <SkeletonBlock class="h-10 w-full rounded-lg" />
                        </div>
                        <SkeletonBlock class="h-10 w-28 rounded-lg" />
                    </div>
                </template>

                <!-- Board (Kanban) -->
                <template v-else-if="variant === 'board'">
                    <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-5 gap-4">
                        <div v-for="col in 5" :key="col" class="card p-4 space-y-3">
                            <SkeletonBlock class="h-4 w-1/2" />
                            <div v-for="n in (col % 3) + 2" :key="n" class="rounded-xl border border-gray-100 p-3 space-y-2">
                                <SkeletonBlock class="h-3.5 w-full" />
                                <SkeletonBlock class="h-3 w-1/2" />
                            </div>
                        </div>
                    </div>
                </template>

                <!-- List (default): toolbar + table rows -->
                <template v-else>
                    <div class="card-padded space-y-5">
                        <div class="flex items-center justify-between gap-4">
                            <SkeletonBlock class="h-10 w-full max-w-sm rounded-lg" />
                            <SkeletonBlock class="h-10 w-24 rounded-lg hidden sm:block" />
                        </div>
                        <div class="divide-y divide-gray-100 border border-gray-100 rounded-xl">
                            <div class="flex gap-6 px-6 py-3 bg-gray-50 rounded-t-xl">
                                <SkeletonBlock v-for="n in 4" :key="n" class="h-3 flex-1" />
                            </div>
                            <div v-for="n in 6" :key="n" class="flex items-center gap-6 px-6 py-4">
                                <SkeletonBlock class="h-4 flex-[2]" />
                                <SkeletonBlock class="h-4 flex-1" />
                                <SkeletonBlock class="h-5 flex-1 rounded-full" />
                                <SkeletonBlock class="h-4 flex-1" />
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
