<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    logs: { type: Object, required: true },
    actions: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const search = ref(props.filters.search || '');

watch(search, debounce((value) => {
    router.get(route('admin.audit-logs.index'), { ...props.filters, search: value || undefined }, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
}, 300));

const filterByAction = (action) => {
    router.get(route('admin.audit-logs.index'), { ...props.filters, action: action || undefined }, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
};

const formattedDate = (dateString) => {
    return new Date(dateString).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
};
</script>

<template>
    <Head title="Audit Log" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="page-heading">Audit Log</h2>
        </template>

        <div class="page-shell">
            <div class="page-container">
                <div class="card-padded">
                    <p class="text-sm text-gray-500 mb-4">Jejak aksi admin yang sensitif: CRUD user/unit, ubah pengaturan, terbitkan/cabut API token, dan login/logout.</p>

                    <div class="flex flex-wrap gap-3 mb-6">
                        <TextInput v-model="search" type="text" placeholder="Cari deskripsi..." class="w-64" />
                        <select :value="filters.action || ''" @change="filterByAction($event.target.value)" class="form-select w-auto">
                            <option value="">Semua Aksi</option>
                            <option v-for="action in actions" :key="action" :value="action">{{ action }}</option>
                        </select>
                    </div>

                    <div class="table-wrap">
                        <table class="table-base">
                            <thead class="table-head">
                                <tr>
                                    <th class="table-head-cell">Waktu</th>
                                    <th class="table-head-cell">Aktor</th>
                                    <th class="table-head-cell">Aksi</th>
                                    <th class="table-head-cell">Deskripsi</th>
                                    <th class="table-head-cell">IP</th>
                                </tr>
                            </thead>
                            <tbody class="table-body">
                                <tr v-if="logs.data.length === 0">
                                    <td colspan="5" class="empty-state">Belum ada audit log.</td>
                                </tr>
                                <tr v-for="log in logs.data" :key="log.id" class="table-row-hover">
                                    <td class="table-cell whitespace-nowrap">{{ formattedDate(log.created_at) }}</td>
                                    <td class="table-cell whitespace-nowrap">{{ log.user?.name || 'Sistem' }}</td>
                                    <td class="table-cell whitespace-nowrap">
                                        <span class="badge-gray font-mono">{{ log.action }}</span>
                                    </td>
                                    <td class="table-cell">{{ log.description }}</td>
                                    <td class="table-cell whitespace-nowrap text-gray-400">{{ log.ip_address || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6" v-if="logs.links.length > 0">
                        <Pagination :links="logs.links" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
