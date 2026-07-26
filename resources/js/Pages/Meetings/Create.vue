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
            <h2 class="page-heading">Jadwalkan Rapat Baru</h2>
        </template>

        <div class="page-shell">
            <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="card-padded">
                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <InputLabel for="title" value="Judul Rapat" />
                            <TextInput id="title" type="text" class="mt-1 block w-full" v-model="form.title" required autofocus />
                            <InputError class="mt-2" :message="form.errors.title" />
                        </div>

                        <div>
                            <InputLabel for="date" value="Tanggal & Waktu" />
                            <TextInput id="date" type="datetime-local" class="mt-1 block w-full" v-model="form.date" required />
                            <InputError class="mt-2" :message="form.errors.date" />
                        </div>

                        <div>
                            <InputLabel for="attendees" value="Peserta" />
                            <TextInput id="attendees" type="text" class="mt-1 block w-full" v-model="form.attendees" required placeholder="Contoh: Budi, Citra, Tim Marketing"/>
                            <InputError class="mt-2" :message="form.errors.attendees" />
                        </div>

                        <div>
                            <InputLabel for="agenda" value="Agenda Singkat" />
                            <textarea id="agenda" class="form-textarea mt-1" v-model="form.agenda" required rows="4"></textarea>
                            <InputError class="mt-2" :message="form.errors.agenda" />
                        </div>

                        <div class="flex items-center justify-end pt-2">
                            <PrimaryButton :class="{ 'opacity-50': form.processing }" :disabled="form.processing">
                                Simpan Jadwal
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
