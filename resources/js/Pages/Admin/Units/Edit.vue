<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    userToEdit: {
        type: Object,
        required: true
    },
    units: {
        type: Array,
        required: true
    }
});

const form = useForm({
    name: props.userToEdit.name,
    phone_number: props.userToEdit.phone_number,
    role: props.userToEdit.role,
    unit_id: props.userToEdit.unit_id,
});

const submit = () => {
    form.put(route('admin.users.update', props.userToEdit.id));
};

// Pilihan untuk dropdown Role
const roles = [
    { id: 'user', name: 'User' },
    { id: 'admin', name: 'Admin' },
    { id: 'superadmin', name: 'Super Admin' }
];

</script>

<template>
    <Head :title="'Edit User: ' + userToEdit.name" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit User: {{ userToEdit.name }}</h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <form @submit.prevent="submit" class="p-6 space-y-6">

                        <!-- Nama -->
                        <div>
                            <InputLabel for="name" value="Nama" />
                            <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <!-- No. HP -->
                        <div>
                            <InputLabel for="phone_number" value="No. HP (untuk WA)" />
                            <TextInput id="phone_number" v-model="form.phone_number" type="text" class="mt-1 block w-full" placeholder="cth: 628123456789" />
                            <InputError class="mt-2" :message="form.errors.phone_number" />
                        </div>

                        <!-- Role -->
                        <div>
                            <InputLabel for="role" value="Role" />
                            <select id="role" v-model="form.role" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.role" />
                        </div>

                        <!-- Unit -->
                        <div>
                            <InputLabel for="unit_id" value="Unit" />
                            <select id="unit_id" v-model="form.unit_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option :value="null">-- Pilih Unit --</option>
                                <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.unit_id" />
                        </div>

                        <!-- Tombol Simpan -->
                        <div class="flex items-center gap-4">
                            <PrimaryButton :disabled="form.processing">Simpan Perubahan</PrimaryButton>
                            <Transition enter-from-class="opacity-0" leave-to-class="opacity-0" class="transition ease-in-out">
                                <p v-if="form.recentlySuccessful" class="text-sm text-gray-600">Tersimpan.</p>
                            </Transition>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
