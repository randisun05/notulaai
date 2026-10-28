<script setup>
import SkeletonBlock from '@/Components/Skeleton/SkeletonBlock.vue';
import { SEARCH_GROUPS, SEARCH_MIN_LENGTH } from '@/Components/Search/searchGroups';
import { searchOpen as open } from '@/Components/Search/searchState';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import debounce from 'lodash/debounce';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const query = ref('');
const results = ref(null);
const loading = ref(false);
const failed = ref(false);
const active = ref(0);
const input = ref(null);
let controller = null;

// Flat list in display order, so arrow keys walk across groups.
const flat = computed(() => (results.value
    ? SEARCH_GROUPS.flatMap((g) => (results.value[g.key] || []).map((item) => ({ ...item, group: g.key })))
    : []));
const hasQuery = computed(() => query.value.trim().length >= SEARCH_MIN_LENGTH);

const fetchResults = debounce(async (q) => {
    controller?.abort();
    controller = new AbortController();
    try {
        const { data } = await axios.get(route('search.quick'), { params: { q }, signal: controller.signal });
        results.value = data;
        failed.value = false;
        active.value = 0;
    } catch (e) {
        if (!axios.isCancel(e)) failed.value = true;
    } finally {
        loading.value = false;
    }
}, 250);

watch(query, (value) => {
    const q = value.trim();
    if (q.length < SEARCH_MIN_LENGTH) {
        fetchResults.cancel();
        controller?.abort();
        results.value = null;
        loading.value = false;
        return;
    }
    loading.value = true;
    fetchResults(q);
});

async function show() {
    open.value = true;
}

// Focus the field whenever the dialog opens, whichever trigger opened it.
watch(open, async (isOpen) => {
    if (!isOpen) return;
    await nextTick();
    input.value?.focus();
    input.value?.select();
});

function close() {
    open.value = false;
}

function go(item) {
    close();
    router.visit(item.url);
}

function seeAll() {
    if (!hasQuery.value) return;
    close();
    router.visit(route('search.index', { q: query.value.trim() }));
}

function onInputKey(e) {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (flat.value.length) active.value = (active.value + 1) % flat.value.length;
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (flat.value.length) active.value = (active.value - 1 + flat.value.length) % flat.value.length;
    } else if (e.key === 'Enter') {
        e.preventDefault();
        flat.value[active.value] ? go(flat.value[active.value]) : seeAll();
    } else if (e.key === 'Escape') {
        close();
    }
}

// Ctrl/Cmd+K anywhere, or "/" when not typing in a field.
function onGlobalKey(e) {
    const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target?.tagName) || e.target?.isContentEditable;
    if ((e.key === 'k' && (e.metaKey || e.ctrlKey)) || (e.key === '/' && !typing && !open.value)) {
        e.preventDefault();
        open.value ? close() : show();
    }
}

onMounted(() => document.addEventListener('keydown', onGlobalKey));
onBeforeUnmount(() => {
    document.removeEventListener('keydown', onGlobalKey);
    fetchResults.cancel();
    controller?.abort();
});

const indexOf = (item) => flat.value.findIndex((f) => f.group === item.group && f.id === item.id);

</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center bg-gray-900/40 px-4 pt-[10vh] sm:pt-[15vh]" @mousedown.self="close">
                <div class="w-full max-w-xl rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 overflow-hidden" role="dialog" aria-modal="true" aria-label="Pencarian">
                    <div class="flex items-center gap-3 px-4 border-b border-gray-100">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400 shrink-0" aria-hidden="true" />
                        <input
                            ref="input"
                            v-model="query"
                            @keydown="onInputKey"
                            type="search"
                            class="h-14 w-full border-0 bg-transparent px-0 text-base text-gray-900 placeholder:text-gray-400 focus:ring-0"
                            placeholder="Cari rapat, task, atau komentar…"
                            role="combobox"
                            aria-controls="search-results"
                            :aria-expanded="flat.length > 0"
                            :aria-activedescendant="flat[active] ? `search-${flat[active].group}-${flat[active].id}` : undefined"
                            autocomplete="off"
                        />
                        <kbd class="hidden sm:inline rounded border border-gray-200 px-1.5 text-[11px] text-gray-400">Esc</kbd>
                    </div>

                    <div id="search-results" class="max-h-[60vh] overflow-y-auto" role="listbox">
                        <p v-if="!hasQuery" class="px-4 py-8 text-center text-sm text-gray-400">
                            Ketik minimal {{ SEARCH_MIN_LENGTH }} huruf. Gunakan ↑ ↓ lalu Enter untuk membuka.
                        </p>

                        <div v-else-if="loading && !results" class="p-4 space-y-4" role="status" aria-busy="true">
                            <span class="sr-only">Mencari…</span>
                            <div v-for="n in 3" :key="n" class="flex gap-3">
                                <SkeletonBlock class="h-8 w-8 rounded-lg shrink-0" />
                                <div class="flex-1 space-y-2">
                                    <SkeletonBlock class="h-3.5 w-2/3" />
                                    <SkeletonBlock class="h-3 w-full" />
                                </div>
                            </div>
                        </div>

                        <p v-else-if="failed" class="px-4 py-8 text-center text-sm text-red-600">Pencarian gagal. Coba lagi.</p>

                        <p v-else-if="flat.length === 0" class="px-4 py-8 text-center text-sm text-gray-500">
                            Tidak ada hasil untuk “{{ query.trim() }}”.
                        </p>

                        <template v-else>
                            <template v-for="group in SEARCH_GROUPS" :key="group.key">
                                <div v-if="results[group.key]?.length" class="py-2">
                                    <p class="px-4 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ group.label }}</p>
                                    <button
                                        v-for="item in results[group.key].map((r) => ({ ...r, group: group.key }))"
                                        :key="item.id"
                                        :id="`search-${group.key}-${item.id}`"
                                        type="button"
                                        role="option"
                                        :aria-selected="indexOf(item) === active"
                                        @click="go(item)"
                                        @mousemove="active = indexOf(item)"
                                        class="w-full flex gap-3 px-4 py-2.5 text-left"
                                        :class="indexOf(item) === active ? 'bg-brand-50' : ''"
                                    >
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-500">
                                            <component :is="group.icon" class="h-4 w-4" aria-hidden="true" />
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-medium text-gray-900 truncate">{{ item.title }}</span>
                                            <span v-if="item.subtitle" class="block text-xs text-gray-500 truncate">{{ item.subtitle }}</span>
                                            <span v-if="item.snippet" class="block text-xs text-gray-500 line-clamp-1">{{ item.snippet }}</span>
                                        </span>
                                    </button>
                                </div>
                            </template>
                        </template>
                    </div>

                    <button
                        v-if="hasQuery"
                        type="button"
                        @click="seeAll"
                        class="block w-full border-t border-gray-100 px-4 py-2.5 text-center text-sm font-medium text-brand-600 hover:bg-gray-50"
                    >
                        Lihat semua hasil untuk “{{ query.trim() }}”
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
