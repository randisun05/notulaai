<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

// Menggunakan sintaks defineProps yang lebih sederhana
const props = defineProps(['user', 'units']);

const form = useForm({
    _method: 'PATCH',
    name: props.user.name,
    email: props.user.email,
    role: props.user.role,
    unit_id: props.user.unit_id,
    phone_number: props.user.phone_number || '',
    nip: props.user.nip || '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.put(route('admin.users.update', props.user.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="'Edit User: ' + user.name" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="page-heading">Edit User: {{ user.name }}</h2>
        </template>

        <div class="page-shell">
            <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="card-padded">
                        <form @submit.prevent="submit" class="space-y-4">
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

                            <div>
                                <InputLabel for="password" value="Password Baru (Kosongkan jika tidak diubah)" />
                                <TextInput id="password" type="password" class="mt-1 block w-full" v-model="form.password" />
                                <InputError class="mt-2" :message="form.errors.password" />
                            </div>

                             <div>
                                <InputLabel for="password_confirmation" value="Konfirmasi Password Baru" />
                                <TextInput id="password_confirmation" type="password" class="mt-1 block w-full" v-model="form.password_confirmation" />
                                <InputError class="mt-2" :message="form.errors.password_confirmation" />
                            </div>


                            <div class="flex justify-end mt-6">
                                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    Simpan Perubahan
                                </PrimaryButton>
                            </div>
                        </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

