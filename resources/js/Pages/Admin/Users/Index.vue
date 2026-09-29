<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3'; // 'router' ditambahkan di sini
import { ref, watch } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    users: {
        type: Object,
        required: true,
    },
    units: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    }
});

const confirmingUserCreation = ref(false);
const search = ref(props.filters.search);
const showDeleteModal = ref(false);
const userToDelete = ref(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    role: 'user',
    unit_id: null,
    phone_number: '',
    nip: '',
});

const openModal = () => {
    confirmingUserCreation.value = true;
};

const closeModal = () => {
    confirmingUserCreation.value = false;
    form.reset();
};

const submit = () => {
    form.post(route('admin.users.store'), {
        onSuccess: () => {
            closeModal();
            form.reset();
        },
    });
};

const openDeleteModal = (user) => {
    userToDelete.value = user;
    showDeleteModal.value = true;
};

const submitDelete = () => {
    useForm({}).delete(route('admin.users.destroy', userToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            userToDelete.value = null;
        },
    });
};

watch(search, debounce((value) => {
    router.get(route('admin.users.index'), { search: value }, { preserveState: true, replace: true });
}, 300));

</script>

<template>
    <Head title="Manajemen User" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="page-heading">Manajemen User</h2>
        </template>

        <div class="page-shell">
            <div class="page-container">
                <div class="card-padded">

                    <div class="flex flex-col sm:flex-row justify-between gap-3 mb-6">
                        <TextInput
                            type="text"
                            class="block w-full md:w-1/2"
                            v-model="search"
                            placeholder="Cari user berdasarkan nama atau email..."
                        />
                        <PrimaryButton @click="openModal">+ Tambah User</PrimaryButton>
                    </div>

                    <div class="table-wrap">
                        <table class="table-base">
                            <thead class="table-head">
                                <tr>
                                    <th scope="col" class="table-head-cell">Nama</th>
                                    <th scope="col" class="table-head-cell">Unit</th>
                                    <th scope="col" class="table-head-cell">Role</th>
                                    <th scope="col" class="relative px-6 py-3"><span class="sr-only">Edit</span></th>
                                </tr>
                            </thead>
                            <tbody class="table-body">
                                <tr v-for="user in users.data" :key="user.id" class="table-row-hover">
                                    <td class="table-cell whitespace-nowrap">
                                        <div class="font-medium text-gray-900">{{ user.name }}</div>
                                        <div class="text-gray-500">{{ user.email }}</div>
                                    </td>
                                    <td class="table-cell whitespace-nowrap">{{ user.unit ? user.unit.name : 'N/A' }}</td>
                                    <td class="table-cell whitespace-nowrap">
                                        <span
                                            :class="{
                                                'badge-green': user.role === 'user',
                                                'badge-blue': user.role === 'admin',
                                                'badge-red': user.role === 'superadmin',
                                                'badge-orange': user.role === 'pimpinan',
                                            }"
                                        >
                                            {{ user.role }}
                                        </span>
                                    </td>
                                    <td class="table-cell whitespace-nowrap text-right font-medium">
                                        <Link :href="route('admin.users.edit', user.id)" class="text-brand-600 hover:text-brand-800">Edit</Link>
                                       <Link @click="openDeleteModal(user)" class="ml-4 text-red-600 hover:text-red-800">Hapus</Link>
                                    </td>
                                </tr>
                                <tr v-if="users.data.length === 0">
                                    <td colspan="4" class="empty-state">
                                        Tidak ada user yang ditemukan.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <Pagination class="mt-6" :links="users.links" />

                </div>
            </div>
        </div>

        <!-- Modal Tambah User -->
        <Modal :show="confirmingUserCreation" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Tambah User Baru</h2>
                <form @submit.prevent="submit" class="mt-6 space-y-4">
                    <div>
                        <InputLabel for="name" value="Nama" />
                        <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" required autofocus />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="email" value="Email" />
                        <TextInput id="email" type="email" class="mt-1 block w-full" v-model="form.email" required />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="password" value="Password" />
                        <TextInput id="password" type="password" class="mt-1 block w-full" v-model="form.password" required />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div>
                        <InputLabel for="role" value="Role" />
                        <select id="role" v-model="form.role" class="form-select mt-1">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                            <option value="pimpinan">Pimpinan (lihat semua unit, hanya baca)</option>
                            <option value="superadmin">Super Admin</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.role" />
                    </div>

                    <div>
                        <InputLabel for="unit" value="Unit (Opsional)" />
                        <select id="unit" v-model="form.unit_id" class="form-select mt-1">
                            <option :value="null">-- Tidak Ada Unit --</option>
                            <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.unit_id" />
                    </div>

                     <div>
                        <InputLabel for="phone_number" value="No. HP (Opsional)" />
                        <TextInput id="phone_number" type="text" class="mt-1 block w-full" v-model="form.phone_number" placeholder="0812..." />
                        <InputError class="mt-2" :message="form.errors.phone_number" />
                    </div>
                    <div class="mt-4">
                        <InputLabel for="nip" value="NIP (Opsional)" />
                        <TextInput id="nip" type="text" class="mt-1 block w-full" v-model="form.nip" placeholder="Dicantumkan di tanda tangan notula" />
                        <InputError class="mt-2" :message="form.errors.nip" />
                    </div>

                    <div class="flex justify-end mt-6">
                        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            Simpan User
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Modal Hapus User -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false" max-width="lg">
             <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Hapus User</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Apakah Anda yakin ingin menghapus user <strong>{{ userToDelete?.name }}</strong>?
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

