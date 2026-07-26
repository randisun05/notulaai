<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
// UBAH IMPORT DI SINI: Kita impor dari 'lodash' utama, bukan 'lodash.debounce'
import { debounce } from 'lodash';

// PERBAIKAN: Menggunakan sintaks array untuk defineProps
// untuk mengatasi masalah kompilasi esbuild.
const props = defineProps(['meetings', 'filters']);

// Menambahkan fallback (|| '') untuk memastikan ref tidak undefined
// jika filters.search tidak ada, yang menggantikan fungsi 'default'
const search = ref(props.filters?.search || '');

// Fungsi debounce ini sekarang seharusnya berfungsi
watch(search, debounce((value) => {
    router.get(route('meetings.index'), { search: value }, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
}, 300)); // 300ms delay

const formattedDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
};

const confirmDelete = (meetingId) => {
    if (confirm('Apakah Anda yakin ingin menghapus rapat ini? Ini akan menghapus semua notula terkait.')) {
        router.delete(route('meetings.destroy', meetingId), {
            preserveScroll: true,
        });
    }
};

</script>

<template>
    <Head title="Indeks Rapat" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Indeks Rapat</h2>
                <Link :href="route('meetings.create')" as="button" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    Jadwalkan Rapat Baru
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">

                        <!-- Search Bar -->
                        <div class="mb-6">
                            <input
                                type="text"
                                v-model="search"
                                placeholder="Cari berdasarkan judul, agenda, atau isi notula..."
                                class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                            />
                        </div>

                        <!-- Meja Rapat -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Judul</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                        <th scope="col" class="relative px-6 py-3">
                                            <span class="sr-only">Aksi</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-if="meetings.data.length === 0">
                                        <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                            Tidak ada rapat ditemukan.
                                        </td>
                                    </tr>
                                    <tr v-for="meeting in meetings.data" :key="meeting.id">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <Link :href="route('meetings.show', meeting.id)" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">
                                                {{ meeting.title }}
                                            </Link>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ formattedDate(meeting.date) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span :class="{
                                                'bg-blue-100 text-blue-800': meeting.status === 'Dijadwalkan',
                                                'bg-yellow-100 text-yellow-800': meeting.status === 'Memproses',
                                                'bg-green-100 text-green-800': meeting.status === 'Selesai Diproses',
                                                'bg-red-100 text-red-800': meeting.status === 'Gagal'
                                            }" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                                {{ meeting.status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ meeting.unit ? meeting.unit.name : 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <Link :href="route('meetings.show', meeting.id)" class="text-indigo-600 hover:text-indigo-900">Lihat</Link>
                                            <button @click.prevent="confirmDelete(meeting.id)" class="ml-4 text-red-600 hover:text-red-900">
                                                Hapus
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-6" v-if="meetings.links.length > 0">
                            <Pagination :links="meetings.links" />
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

