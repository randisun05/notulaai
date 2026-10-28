<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { kindMeta, timeAgo } from '@/Components/Notifications/notificationKinds';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    notifications: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const setFilter = (unread) => {
    router.get(route('notifications.index'), { unread: unread ? 1 : undefined }, { preserveState: true, replace: true });
};

const openItem = async (item) => {
    if (!item.read) {
        await axios.post(route('notifications.read', item.id)).catch(() => {});
    }
    if (item.url) router.visit(item.url);
};

const markAllRead = () => {
    router.post(route('notifications.read-all'), {}, { preserveScroll: true });
};

const formatDateTime = (iso) => new Date(iso).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
</script>

<template>
    <Head title="Notifikasi" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="page-heading">Notifikasi</h2>
                <button type="button" class="btn-secondary" @click="markAllRead">Tandai semua dibaca</button>
            </div>
        </template>

        <div class="page-shell">
            <div class="page-container">
                <div class="card-padded">
                    <div class="inline-flex rounded-lg border border-gray-200 bg-white p-0.5 mb-5" role="group" aria-label="Filter">
                        <button
                            v-for="opt in [{ label: 'Semua', unread: false }, { label: 'Belum dibaca', unread: true }]"
                            :key="opt.label"
                            type="button"
                            @click="setFilter(opt.unread)"
                            :aria-pressed="filters.unread === opt.unread"
                            :class="['px-3 py-1.5 text-sm font-medium rounded-md transition', filters.unread === opt.unread ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-50']"
                        >
                            {{ opt.label }}
                        </button>
                    </div>

                    <p v-if="notifications.data.length === 0" class="empty-state">
                        {{ filters.unread ? 'Tidak ada notifikasi yang belum dibaca.' : 'Belum ada notifikasi.' }}
                    </p>

                    <ul v-else class="divide-y divide-gray-100 border border-gray-100 rounded-xl overflow-hidden">
                        <li v-for="item in notifications.data" :key="item.id">
                            <button
                                type="button"
                                @click="openItem(item)"
                                class="w-full flex gap-4 px-5 py-4 text-left hover:bg-gray-50 focus:bg-gray-50 focus:outline-none transition"
                                :class="item.read ? '' : 'bg-brand-50/40'"
                            >
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full" :class="kindMeta(item.kind).tint">
                                    <component :is="kindMeta(item.kind).icon" class="h-5 w-5" aria-hidden="true" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm text-gray-900" :class="item.read ? '' : 'font-semibold'">{{ item.title }}</span>
                                    <span v-if="item.body" class="block text-sm text-gray-600 mt-0.5">{{ item.body }}</span>
                                    <span class="block text-xs text-gray-400 mt-1" :title="formatDateTime(item.created_at)">{{ timeAgo(item.created_at) }}</span>
                                </span>
                                <span v-if="!item.read" class="badge-blue self-center shrink-0">Baru</span>
                            </button>
                        </li>
                    </ul>

                    <Pagination class="mt-6" :links="notifications.links" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
