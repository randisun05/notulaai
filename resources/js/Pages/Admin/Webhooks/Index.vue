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
            <h2 class="page-heading">Webhooks</h2>
        </template>

        <div class="page-shell">
            <div class="page-container">
                <div class="card-padded">
                    <p class="text-sm text-gray-500 mb-4">
                        Kirim notifikasi HTTP POST otomatis ke sistem eksternal saat event tertentu terjadi (task dibuat,
                        status berubah, disetujui, atau notula selesai diproses). Setiap payload ditandatangani HMAC-SHA256
                        memakai secret webhook (header <code>X-Notula-Signature</code>).
                    </p>

                    <div class="flex justify-end mb-6">
                        <PrimaryButton @click="showCreateModal = true">+ Tambah Webhook</PrimaryButton>
                    </div>

                    <div class="table-wrap">
                        <table class="table-base">
                            <thead class="table-head">
                                <tr>
                                    <th class="table-head-cell">Nama</th>
                                    <th class="table-head-cell">Unit</th>
                                    <th class="table-head-cell">URL</th>
                                    <th class="table-head-cell">Events</th>
                                    <th class="table-head-cell">Secret</th>
                                    <th class="table-head-cell">Status</th>
                                    <th class="relative px-6 py-3"><span class="sr-only">Aksi</span></th>
                                </tr>
                            </thead>
                            <tbody class="table-body">
                                <tr v-if="webhooks.length === 0">
                                    <td colspan="7" class="empty-state">Belum ada webhook.</td>
                                </tr>
                                <tr v-for="webhook in webhooks" :key="webhook.id" class="table-row-hover">
                                    <td class="table-cell whitespace-nowrap font-medium text-gray-900">{{ webhook.name }}</td>
                                    <td class="table-cell whitespace-nowrap">{{ webhook.unit?.name }}</td>
                                    <td class="table-cell max-w-xs truncate">{{ webhook.url }}</td>
                                    <td class="table-cell">
                                        <span v-for="event in webhook.events" :key="event" class="badge-gray font-mono mr-1 mb-1">{{ event }}</span>
                                    </td>
                                    <td class="table-cell text-xs text-gray-400 font-mono select-all">{{ webhook.secret }}</td>
                                    <td class="table-cell whitespace-nowrap">
                                        <button @click="toggleActive(webhook)" :class="webhook.is_active ? 'badge-green' : 'badge-gray'">
                                            {{ webhook.is_active ? 'Aktif' : 'Nonaktif' }}
                                        </button>
                                    </td>
                                    <td class="table-cell whitespace-nowrap text-right">
                                        <button @click="openDeleteModal(webhook)" class="text-red-600 hover:text-red-800 font-medium">Hapus</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
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
                    <select id="unit_id" v-model="form.unit_id" class="form-select mt-1" required>
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
                            <input type="checkbox" :value="event" v-model="form.events" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
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
