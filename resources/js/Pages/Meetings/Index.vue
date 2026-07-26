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
                <div>
                    <h2 class="page-heading">Indeks Rapat</h2>
                    <p class="page-subheading">Kelola dan cari seluruh riwayat rapat unit Anda.</p>
                </div>
                <Link :href="route('meetings.create')" as="button" class="btn-primary">
                    + Jadwalkan Rapat
                </Link>
            </div>
        </template>

        <div class="page-shell">
            <div class="page-container">
                <div class="card-padded">

                    <div class="mb-6">
                        <input
                            type="text"
                            v-model="search"
                            placeholder="Cari berdasarkan judul, agenda, atau isi notula..."
                            class="form-input"
                        />
                    </div>

                    <div class="table-wrap">
                        <table class="table-base">
                            <thead class="table-head">
                                <tr>
                                    <th scope="col" class="table-head-cell">Judul</th>
                                    <th scope="col" class="table-head-cell">Tanggal</th>
                                    <th scope="col" class="table-head-cell">Status</th>
                                    <th scope="col" class="table-head-cell">Unit</th>
                                    <th scope="col" class="relative px-6 py-3">
                                        <span class="sr-only">Aksi</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="table-body">
                                <tr v-if="meetings.data.length === 0">
                                    <td colspan="5" class="empty-state">
                                        Tidak ada rapat ditemukan.
                                    </td>
                                </tr>
                                <tr v-for="meeting in meetings.data" :key="meeting.id" class="table-row-hover">
                                    <td class="table-cell whitespace-nowrap">
                                        <Link :href="route('meetings.show', meeting.id)" class="font-medium text-brand-600 hover:text-brand-800">
                                            {{ meeting.title }}
                                        </Link>
                                    </td>
                                    <td class="table-cell whitespace-nowrap">
                                        {{ formattedDate(meeting.date) }}
                                    </td>
                                    <td class="table-cell whitespace-nowrap">
                                        <span :class="{
                                            'badge-blue': meeting.status === 'Dijadwalkan',
                                            'badge-yellow': meeting.status === 'Memproses',
                                            'badge-green': meeting.status === 'Selesai Diproses',
                                            'badge-red': meeting.status === 'Gagal',
                                        }">
                                            {{ meeting.status }}
                                        </span>
                                    </td>
                                    <td class="table-cell whitespace-nowrap">
                                        {{ meeting.unit ? meeting.unit.name : 'N/A' }}
                                    </td>
                                    <td class="table-cell whitespace-nowrap text-right font-medium">
                                        <Link :href="route('meetings.show', meeting.id)" class="btn-link">Lihat</Link>
                                        <button @click.prevent="confirmDelete(meeting.id)" class="ml-4 text-red-600 hover:text-red-800 text-sm font-medium">
                                            Hapus
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6" v-if="meetings.links.length > 0">
                        <Pagination :links="meetings.links" />
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

