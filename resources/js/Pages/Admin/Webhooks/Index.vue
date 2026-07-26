<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    webhooks: { type: Array, required: true },
    units: { type: Array, required: true },
    availableEvents: { type: Array, required: true },
});

const showCreateModal = ref(false);
const showDeleteModal = ref(false);
const webhookToDelete = ref(null);

const form = useForm({
    name: '',
    unit_id: '',
    url: '',
    events: [],
});

const submitCreate = () => {
    form.post(route('admin.webhooks.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        },
    });
};

const toggleActive = (webhook) => {
    useForm({ is_active: !webhook.is_active }).patch(route('admin.webhooks.update', webhook.id), { preserveScroll: true });
};

const openDeleteModal = (webhook) => {
    webhookToDelete.value = webhook;
    showDeleteModal.value = true;
};

const submitDelete = () => {
    useForm({}).delete(route('admin.webhooks.destroy', webhookToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            webhookToDelete.value = null;
        },
    });
};
</script>

<template>
    <Head title="Webhooks" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Webhooks</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <p class="text-sm text-gray-500 mb-4">
                            Kirim notifikasi HTTP POST otomatis ke sistem eksternal saat event tertentu terjadi (task dibuat,
                            status berubah, disetujui, atau notula selesai diproses). Setiap payload ditandatangani HMAC-SHA256
                            memakai secret webhook (header <code>X-Notula-Signature</code>).
                        </p>

                        <div class="flex justify-end mb-6">
                            <PrimaryButton @click="showCreateModal = true">Tambah Webhook</PrimaryButton>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">URL</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Events</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Secret</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="relative px-6 py-3"><span class="sr-only">Aksi</span></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-if="webhooks.length === 0">
                                        <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">Belum ada webhook.</td>
                                    </tr>
                                    <tr v-for="webhook in webhooks" :key="webhook.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ webhook.name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ webhook.unit?.name }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">{{ webhook.url }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            <span v-for="event in webhook.events" :key="event" class="inline-block text-xs px-1.5 py-0.5 mr-1 mb-1 rounded bg-gray-100 font-mono">{{ event }}</span>
                                        </td>
                                        <td class="px-6 py-4 text-xs text-gray-400 font-mono select-all">{{ webhook.secret }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <button @click="toggleActive(webhook)" :class="['text-xs px-2 py-0.5 rounded-full', webhook.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
                                                {{ webhook.is_active ? 'Aktif' : 'Nonaktif' }}
                                            </button>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                            <button @click="openDeleteModal(webhook)" class="text-red-600 hover:text-red-900">Hapus</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <form @submit.prevent="submitCreate" class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Tambah Webhook</h2>

                <div class="mt-4">
                    <InputLabel for="name" value="Nama" />
                    <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required autofocus />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div class="mt-4">
                    <InputLabel for="unit_id" value="Unit" />
                    <select id="unit_id" v-model="form.unit_id" class="mt-1 block w-full border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500" required>
                        <option value="" disabled>Pilih unit</option>
                        <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.unit_id" />
                </div>

                <div class="mt-4">
                    <InputLabel for="url" value="URL Endpoint" />
                    <TextInput id="url" v-model="form.url" type="url" class="mt-1 block w-full" placeholder="https://contoh.com/webhook" required />
                    <InputError class="mt-2" :message="form.errors.url" />
                </div>

                <div class="mt-4">
                    <InputLabel value="Events" />
                    <div class="mt-1 space-y-1">
                        <label v-for="event in availableEvents" :key="event" class="flex items-center gap-2 text-sm">
                            <input type="checkbox" :value="event" v-model="form.events" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <span class="font-mono">{{ event }}</span>
                        </label>
                    </div>
                    <InputError class="mt-2" :message="form.errors.events" />
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showCreateModal = false">Batal</SecondaryButton>
                    <PrimaryButton class="ml-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">Simpan</PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="showDeleteModal" @close="showDeleteModal = false" max-width="lg">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Hapus Webhook</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Yakin hapus webhook <strong>{{ webhookToDelete?.name }}</strong>? Aksi ini tidak dapat dibatalkan.
                </p>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showDeleteModal = false">Batal</SecondaryButton>
                    <DangerButton class="ml-3" @click="submitDelete">Hapus</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
