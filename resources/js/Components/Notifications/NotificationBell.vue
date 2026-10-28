<script setup>
import { kindMeta, timeAgo } from '@/Components/Notifications/notificationKinds';
import SkeletonBlock from '@/Components/Skeleton/SkeletonBlock.vue';
import { BellIcon } from '@heroicons/vue/24/outline';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const page = usePage();

// Seeded from the shared Inertia prop (fresh on every page visit), then kept
// current by a light poll while the tab is visible.
const unreadCount = ref(page.props.auth.unread_notifications ?? 0);
watch(() => page.props.auth.unread_notifications, (count) => {
    if (typeof count === 'number') unreadCount.value = count;
});

const open = ref(false);
const loading = ref(false);
const items = ref([]);
const loadError = ref(false);

const badge = computed(() => (unreadCount.value > 99 ? '99+' : String(unreadCount.value)));

async function load() {
    loading.value = items.value.length === 0;
    loadError.value = false;
    try {
        const { data } = await axios.get(route('notifications.recent'));
        items.value = data.items;
        unreadCount.value = data.unread_count;
    } catch {
        loadError.value = true;
    } finally {
        loading.value = false;
    }
}

function toggle() {
    open.value = !open.value;
    if (open.value) load();
}

async function openItem(item) {
    open.value = false;
    if (!item.read) {
        item.read = true;
        unreadCount.value = Math.max(0, unreadCount.value - 1);
        axios.post(route('notifications.read', item.id)).catch(() => {});
    }
    if (item.url) router.visit(item.url);
}

async function markAllRead() {
    items.value.forEach((i) => { i.read = true; });
    unreadCount.value = 0;
    await axios.post(route('notifications.read-all'), {}, { headers: { Accept: 'application/json' } }).catch(() => {});
}

const POLL_MS = 60_000;
let timer = null;
const poll = async () => {
    if (document.visibilityState !== 'visible') return;
    try {
        const { data } = await axios.get(route('notifications.recent'));
        unreadCount.value = data.unread_count;
        if (open.value) items.value = data.items;
    } catch { /* offline or logged out: keep the last count */ }
};
const onKey = (e) => { if (e.key === 'Escape') open.value = false; };

onMounted(() => {
    timer = setInterval(poll, POLL_MS);
    document.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => {
    clearInterval(timer);
    document.removeEventListener('keydown', onKey);
});
</script>

<template>
    <div class="relative">
        <button
            type="button"
            @click="toggle"
            class="relative flex items-center justify-center h-9 w-9 rounded-full text-gray-500 hover:text-gray-800 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-brand-500 transition"
            :aria-label="unreadCount ? `Notifikasi, ${unreadCount} belum dibaca` : 'Notifikasi'"
            :aria-expanded="open"
        >
            <BellIcon class="h-6 w-6" aria-hidden="true" />
            <span
                v-if="unreadCount > 0"
                class="absolute -top-0.5 -right-0.5 min-w-[1.25rem] h-5 px-1 rounded-full bg-red-600 text-white text-[11px] font-semibold leading-5 text-center ring-2 ring-white"
                aria-hidden="true"
            >{{ badge }}</span>
        </button>

        <div v-show="open" class="fixed inset-0 z-40" @click="open = false"></div>

        <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-show="open"
                class="fixed inset-x-4 top-16 sm:absolute sm:inset-x-auto sm:top-auto sm:right-0 z-50 sm:mt-2 sm:w-[22rem] origin-top-right rounded-xl bg-white shadow-lg ring-1 ring-black/5 overflow-hidden"
                role="dialog"
                aria-label="Notifikasi"
            >
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                    <p class="text-sm font-semibold text-gray-900">Notifikasi</p>
                    <button v-if="unreadCount > 0" type="button" class="btn-link text-xs" @click="markAllRead">Tandai semua dibaca</button>
                </div>

                <div class="max-h-96 overflow-y-auto">
                    <div v-if="loading" class="divide-y divide-gray-100" role="status" aria-busy="true">
                        <span class="sr-only">Memuat notifikasi...</span>
                        <div v-for="n in 3" :key="n" class="flex gap-3 px-4 py-3">
                            <SkeletonBlock class="h-8 w-8 rounded-full shrink-0" />
                            <div class="flex-1 space-y-2">
                                <SkeletonBlock class="h-3.5 w-5/6" />
                                <SkeletonBlock class="h-3 w-1/3" />
                            </div>
                        </div>
                    </div>
                    <p v-else-if="loadError" class="px-4 py-8 text-center text-sm text-red-600">Gagal memuat notifikasi.</p>
                    <p v-else-if="items.length === 0" class="px-4 py-10 text-center text-sm text-gray-400">Belum ada notifikasi.</p>
                    <ul v-else class="divide-y divide-gray-100">
                        <li v-for="item in items" :key="item.id">
                            <button
                                type="button"
                                @click="openItem(item)"
                                class="w-full flex gap-3 px-4 py-3 text-left hover:bg-gray-50 focus:bg-gray-50 focus:outline-none transition"
                                :class="item.read ? '' : 'bg-brand-50/40'"
                            >
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full" :class="kindMeta(item.kind).tint">
                                    <component :is="kindMeta(item.kind).icon" class="h-4 w-4" aria-hidden="true" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm text-gray-900" :class="item.read ? '' : 'font-semibold'">{{ item.title }}</span>
                                    <span v-if="item.body" class="block text-xs text-gray-500 truncate">{{ item.body }}</span>
                                    <span class="block text-xs text-gray-400 mt-0.5">{{ timeAgo(item.created_at) }}</span>
                                </span>
                                <span v-if="!item.read" class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand-600" aria-label="Belum dibaca"></span>
                            </button>
                        </li>
                    </ul>
                </div>

                <Link :href="route('notifications.index')" class="block px-4 py-2.5 text-center text-sm font-medium text-brand-600 hover:bg-gray-50 border-t border-gray-100" @click="open = false">
                    Lihat semua notifikasi
                </Link>
            </div>
        </Transition>
    </div>
</template>
