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
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Audit Log</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <p class="text-sm text-gray-500 mb-4">Jejak aksi admin yang sensitif: CRUD user/unit, ubah pengaturan, terbitkan/cabut API token, dan login/logout.</p>

                        <div class="flex flex-wrap gap-3 mb-6">
                            <TextInput v-model="search" type="text" placeholder="Cari deskripsi..." class="w-64" />
                            <select :value="filters.action || ''" @change="filterByAction($event.target.value)" class="text-sm border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Semua Aksi</option>
                                <option v-for="action in actions" :key="action" :value="action">{{ action }}</option>
                            </select>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aktor</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deskripsi</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-if="logs.data.length === 0">
                                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">Belum ada audit log.</td>
                                    </tr>
                                    <tr v-for="log in logs.data" :key="log.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formattedDate(log.created_at) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ log.user?.name || 'Sistem' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 font-mono">{{ log.action }}</span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-700">{{ log.description }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">{{ log.ip_address || '-' }}</td>
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
        </div>
    </AuthenticatedLayout>
</template>
