<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    meeting: Object,
});

// 'YYYY-MM-DD HH:MM:SS' -> 'YYYY-MM-DDTHH:MM' untuk input datetime-local (sama dengan Create.vue),
// supaya jam rapat tidak hilang (jadi 00:00) setiap kali rapat diedit.
const formattedDate = props.meeting.date ? props.meeting.date.replace(' ', 'T').slice(0, 16) : '';

const form = useForm({
    title: props.meeting.title,
    date: formattedDate,
    agenda: props.meeting.agenda,
    attendees: props.meeting.attendees,
});

const submitUpdate = () => {
    form.put(route('meetings.update', props.meeting.id));
};
</script>

<template>
    <Head title="Edit Rapat" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="page-heading">Edit Rapat: {{ meeting.title }}</h2>
        </template>

        <div class="page-shell">
            <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="card-padded">
                    <form @submit.prevent="submitUpdate" class="space-y-4">
                        <div>
                            <InputLabel for="title" value="Judul Rapat" />
                            <TextInput id="title" type="text" class="mt-1 block w-full" v-model="form.title" required autofocus />
                            <InputError class="mt-2" :message="form.errors.title" />
                        </div>

                        <div>
                            <InputLabel for="date" value="Tanggal Rapat" />
                            <TextInput id="date" type="datetime-local" class="mt-1 block w-full" v-model="form.date" required />
                            <InputError class="mt-2" :message="form.errors.date" />
                        </div>

                        <div>
                            <InputLabel for="agenda" value="Agenda Singkat" />
                            <textarea id="agenda" class="form-textarea mt-1" v-model="form.agenda" required rows="4"></textarea>
                            <InputError class="mt-2" :message="form.errors.agenda" />
                        </div>

                        <div>
                            <InputLabel for="attendees" value="Peserta" />
                            <TextInput id="attendees" type="text" class="mt-1 block w-full" v-model="form.attendees" required />
                            <InputError class="mt-2" :message="form.errors.attendees" />
                        </div>

                        <div class="flex items-center justify-end pt-2">
                            <PrimaryButton :class="{ 'opacity-50': form.processing }" :disabled="form.processing">
                                Simpan Perubahan
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
