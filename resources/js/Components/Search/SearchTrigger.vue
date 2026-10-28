<script setup>
import { openSearch } from '@/Components/Search/searchState';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';

const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);
</script>

<template>
    <button
        type="button"
        @click="openSearch"
        class="flex items-center gap-2 h-9 rounded-full border border-gray-200 bg-white text-sm text-gray-500 hover:border-brand-300 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-brand-500 transition w-9 justify-center lg:w-56 lg:justify-start lg:px-3"
        aria-label="Cari rapat, task, atau komentar"
        aria-keyshortcuts="Control+K Meta+K /"
    >
        <MagnifyingGlassIcon class="h-5 w-5 shrink-0" aria-hidden="true" />
        <span class="hidden lg:inline">Cari…</span>
        <kbd class="hidden lg:inline ms-auto rounded border border-gray-200 px-1.5 text-[11px] font-sans text-gray-400">{{ isMac ? '⌘' : 'Ctrl' }} K</kbd>
    </button>
</template>
