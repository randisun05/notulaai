<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    DocumentTextIcon,
    CheckCircleIcon,
    UsersIcon
} from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const props = defineProps({
    stats: {
        type: Object,
        required: true,
    },
    recentMeetings: {
        type: Array,
        required: true,
    }
});

// Helper untuk format tanggal
const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

// Data untuk card statistik
const statCards = computed(() => [
    { name: 'Total Rapat', href: route('meetings.index'), icon: DocumentTextIcon, stat: props.stats.total_meetings },
    { name: 'Rapat Diproses', href: route('meetings.index'), icon: CheckCircleIcon, stat: props.stats.processed_meetings },
    { name: 'Total User (di Unit Anda)', href: '#', icon: UsersIcon, stat: props.stats.total_users }, // Nanti bisa diarahkan ke halaman user
]);

</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <!-- Card Statistik -->
                <div>
                    <h3 class="text-base font-semibold leading-6 text-gray-900">Ringkasan</h3>
                    <dl class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-3">
                        <div v-for="item in statCards" :key="item.name" class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
                            <dt class="truncate text-sm font-medium text-gray-500">
                                <component :is="item.icon" class="h-6 w-6 text-gray-400 inline-block mr-2" aria-hidden="true" />
                                {{ item.name }}
                            </dt>
                            <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">{{ item.stat }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Daftar Rapat Terbaru -->
                <div class="mt-12">
                     <h3 class="text-base font-semibold leading-6 text-gray-900 mb-5">Rapat Terbaru</h3>
                     <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Judul Rapat</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="relative px-6 py-3">
                                            <span class="sr-only">Aksi</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="meeting in recentMeetings" :key="meeting.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ meeting.title }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDate(meeting.date) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                  :class="{
                                                    'bg-blue-100 text-blue-800': meeting.status === 'Dijadwalkan',
                                                    'bg-yellow-100 text-yellow-800': meeting.status === 'Memproses',
                                                    'bg-green-100 text-green-800': meeting.status === 'Selesai Diproses',
                                                    'bg-red-100 text-red-800': meeting.status === 'Gagal'
                                                  }">
                                                {{ meeting.status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <Link :href="route('meetings.show', meeting.id)" class="text-indigo-600 hover:text-indigo-900">Lihat Detail</Link>
                                        </td>
                                    </tr>
                                    <tr v-if="recentMeetings.length === 0">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" colspan="4">
                                            Belum ada rapat yang dijadwalkan.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                     </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

