<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gradient-to-b from-brand-50 via-white to-white">
        <div>
            <Link href="/" class="flex items-center gap-2">
                <ApplicationLogo class="w-12 h-12 fill-current text-brand-600" />
                <span class="text-xl font-bold text-gray-900 tracking-tight">AI Notula</span>
            </Link>
        </div>

        <div class="w-full sm:max-w-md mt-8 px-8 py-8 card">
            <slot />
        </div>
    </div>
</template>
