<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

// PERBAIKAN: Menggunakan sintaks defineProps yang lebih eksplisit
defineProps({
    units: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: false, // Filter biasanya opsional
    },
});

const form = useForm({
    name: '',
});
// ... rest of the script setup ...
const editForm = useForm({
    id: null,
    name: '',
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const unitToDelete = ref(null);

const submitCreate = () => {
    form.post(route('admin.units.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        },
    });
};

const openEditModal = (unit) => {
    editForm.id = unit.id;
    editForm.name = unit.name;
    showEditModal.value = true;
};

const submitEdit = () => {
    editForm.put(route('admin.units.update', editForm.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            editForm.reset();
        },
    });
};

const openDeleteModal = (unit) => {
    unitToDelete.value = unit;
    showDeleteModal.value = true;
};

const submitDelete = () => {
    useForm({}).delete(route('admin.units.destroy', unitToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            unitToDelete.value = null;
        },
    });
};

</script>

<template>
    <Head title="Manajemen Unit" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="page-heading">Manajemen Unit</h2>
        </template>

        <div class="page-shell">
            <div class="page-container">
                <div class="card-padded">

                    <div class="flex justify-between items-center mb-6">
                        <h3 class="card-title">Daftar Unit</h3>
                        <PrimaryButton @click="showCreateModal = true">+ Tambah Unit</PrimaryButton>
                    </div>

                    <div class="table-wrap">
                        <table class="table-base">
                            <thead class="table-head">
                                <tr>
                                    <th scope="col" class="table-head-cell">Nama Unit</th>
                                    <th scope="col" class="relative px-6 py-3">
                                        <span class="sr-only">Aksi</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="table-body">
                                <tr v-for="unit in units.data" :key="unit.id" class="table-row-hover">
                                    <td class="table-cell whitespace-nowrap font-medium text-gray-900">{{ unit.name }}</td>
                                    <td class="table-cell whitespace-nowrap text-right font-medium">
                                        <button @click="openEditModal(unit)" class="text-brand-600 hover:text-brand-800 mr-4">Edit</button>
                                        <button @click="openDeleteModal(unit)" class="text-red-600 hover:text-red-800">Hapus</button>
                                    </td>
                                </tr>
                                <tr v-if="units.data.length === 0">
                                    <td class="empty-state" colspan="2">
                                        Belum ada data unit.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>

        <!-- Modal Tambah Unit -->
        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <form @submit.prevent="submitCreate" class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Tambah Unit Baru</h2>
                <div class="mt-6">
                    <InputLabel for="name" value="Nama Unit" />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showCreateModal = false">Batal</SecondaryButton>
                    <PrimaryButton class="ml-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        Simpan
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Modal Edit Unit -->
        <Modal :show="showEditModal" @close="showEditModal = false">
            <form @submit.prevent="submitEdit" class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Edit Unit</h2>
                <div class="mt-6">
                    <InputLabel for="edit_name" value="Nama Unit" />
                    <TextInput id="edit_name" v-model="editForm.name" type="text" class="mt-1 block w-full" required />
                    <InputError class="mt-2" :message="editForm.errors.name" />
                </div>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showEditModal = false">Batal</SecondaryButton>
                    <PrimaryButton class="ml-3" :class="{ 'opacity-25': editForm.processing }" :disabled="editForm.processing">
                        Perbarui
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Modal Hapus Unit -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false" max-width="lg">
             <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Hapus Unit</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Apakah Anda yakin ingin menghapus unit **{{ unitToDelete?.name }}**?
                    <span class="font-bold text-red-600">Aksi ini tidak dapat dibatalkan.</span>
                </p>
                 <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showDeleteModal = false">Batal</SecondaryButton>
                    <DangerButton class="ml-3" @click="submitDelete">Hapus</DangerButton>
                </div>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>

