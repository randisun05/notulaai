<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    title: '',
    date: '',
    attendees: '',
    agenda: '',
});

const submit = () => {
    form.post(route('meetings.store'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Jadwalkan Rapat Baru" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Jadwalkan Rapat Baru</h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <form @submit.prevent="submit">
                            <div>
                                <InputLabel for="title" value="Judul Rapat" />
                                <TextInput id="title" type="text" class="mt-1 block w-full" v-model="form.title" required autofocus />
                                <InputError class="mt-2" :message="form.errors.title" />
                            </div>

                            <div class="mt-4">
                                <InputLabel for="date" value="Tanggal & Waktu" />
                                <TextInput id="date" type="datetime-local" class="mt-1 block w-full" v-model="form.date" required />
                                <InputError class="mt-2" :message="form.errors.date" />
                            </div>

                            <div class="mt-4">
                                <InputLabel for="attendees" value="Peserta" />
                                <TextInput id="attendees" type="text" class="mt-1 block w-full" v-model="form.attendees" required placeholder="Contoh: Budi, Citra, Tim Marketing"/>
                                <InputError class="mt-2" :message="form.errors.attendees" />
                            </div>

                             <div class="mt-4">
                                <InputLabel for="agenda" value="Agenda Singkat" />
                                <textarea id="agenda" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" v-model="form.agenda" required rows="4"></textarea>
                                <InputError class="mt-2" :message="form.errors.agenda" />
                            </div>

                            <div class="flex items-center justify-end mt-4">
                                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    Simpan Jadwal
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
