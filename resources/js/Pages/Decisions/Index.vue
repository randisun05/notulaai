<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import FollowUpBadge from '@/Pages/Meetings/Partials/FollowUpBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';
import { debounce } from 'lodash';

const props = defineProps({
    decisions: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Object, default: () => ({}) },
    units: { type: Array, default: () => [] },
});

const form = reactive({
    q: props.filters.q ?? '',
    unit_id: props.filters.unit_id ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    status: props.filters.status ?? '',
});

const apply = () => {
    const query = Object.fromEntries(Object.entries(form).filter(([, v]) => v !== '' && v !== null));
    router.get(route('decisions.index'), query, { preserveState: true, replace: true, preserveScroll: true });
};
watch(() => form.q, debounce(apply, 350));
watch(() => [form.unit_id, form.from, form.to, form.status], apply);

const reset = () => {
    Object.assign(form, { q: '', unit_id: '', from: '', to: '', status: '' });
};

const date = (d) => (d ? new Date(String(d).replace(' ', 'T')).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '');
</script>

<template>
    <Head title="Daftar Keputusan" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="page-heading">Daftar Keputusan</h2>
                <p class="page-subheading">Semua keputusan rapat{{ units.length ? ' seluruh unit' : ' unit Anda' }}, beserta status tindak lanjutnya.</p>
            </div>
        </template>

        <div class="page-shell">
            <div class="page-container">
                <div class="card-padded">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-6">
                        <input v-model="form.q" type="search" placeholder="Cari keputusan atau judul rapat..." class="form-input md:col-span-2" />
                        <select v-if="units.length" v-model="form.unit_id" class="form-select">
                            <option value="">Semua unit</option>
                            <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                        <select v-model="form.status" class="form-select">
                            <option value="">Semua status</option>
                            <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <input v-model="form.from" type="date" class="form-input" title="Dari tanggal rapat" />
                        <input v-model="form.to" type="date" class="form-input" title="Sampai tanggal rapat" />
                    </div>
                    <button type="button" class="mt-2 text-xs text-gray-500 underline" @click="reset">Hapus filter</button>
                </div>

                <div class="card mt-6">
                    <p v-if="!decisions.data.length" class="empty-state p-6">
                        Belum ada keputusan yang cocok. Keputusan tercatat otomatis setiap kali notula rapat selesai diproses,
                        dan diganti kesimpulan notulen resmi begitu disahkan.
                    </p>
                    <ul v-else class="divide-y divide-gray-100">
                        <li v-for="d in decisions.data" :key="d.id" class="p-4 sm:px-6">
                            <p class="text-sm text-gray-900">{{ d.text }}</p>
                            <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                                <FollowUpBadge :follow-up="d.follow_up" />
                                <Link :href="route('meetings.show', d.meeting.id)" class="text-brand-600 hover:text-brand-800">{{ d.meeting.title }}</Link>
                                <span>{{ date(d.meeting.date) }}</span>
                                <span v-if="units.length && d.meeting.unit">{{ d.meeting.unit.name }}</span>
                                <span v-if="d.source === 'notula'" class="text-green-700" title="Sesuai notulen resmi yang disahkan">✓ Notulen resmi</span>
                            </div>
                            <p v-if="d.follow_up.title" class="mt-1 text-xs text-gray-500">Tindak lanjut: {{ d.follow_up.title }}</p>
                        </li>
                    </ul>
                </div>

                <div class="mt-4">
                    <Pagination :links="decisions.links" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
