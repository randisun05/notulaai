<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    task: { type: Object, required: true },
    units: { type: Array, default: () => [] },
    users: { type: Array, required: true },
    priorities: { type: Array, required: true },
    isSuperadmin: { type: Boolean, default: false },
});

const form = useForm({
    title: props.task.title,
    description: props.task.description || '',
    unit_id: props.task.unit_id,
    assignee_id: props.task.assignee_id || '',
    priority: props.task.priority,
    deadline: props.task.deadline || '',
});

const assigneeOptions = computed(() => {
    if (!props.isSuperadmin) return props.users;
    return props.users.filter((u) => u.unit_id === Number(form.unit_id));
});

const submit = () => {
    form.put(route('tasks.update', props.task.id));
};
</script>

<template>
    <Head :title="`Edit ${task.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="page-heading">Edit Task</h2>
        </template>

        <div class="page-shell">
            <div class="page-container max-w-2xl">
                <div class="card-padded">
                    <form @submit.prevent="submit" class="space-y-5">
                        <div>
                            <InputLabel for="title" value="Judul Task" />
                            <TextInput id="title" v-model="form.title" class="mt-1 block w-full" required autofocus />
                            <InputError class="mt-1" :message="form.errors.title" />
                        </div>

                        <div>
                            <InputLabel for="description" value="Deskripsi (opsional)" />
                            <textarea id="description" v-model="form.description" rows="4" class="form-textarea mt-1"></textarea>
                            <InputError class="mt-1" :message="form.errors.description" />
                        </div>

                        <div v-if="isSuperadmin">
                            <InputLabel for="unit_id" value="Unit" />
                            <select id="unit_id" v-model="form.unit_id" class="form-select mt-1">
                                <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.unit_id" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="assignee_id" value="PIC (opsional)" />
                                <select id="assignee_id" v-model="form.assignee_id" class="form-select mt-1">
                                    <option value="">Belum ditentukan</option>
                                    <option v-for="user in assigneeOptions" :key="user.id" :value="user.id">{{ user.name }}</option>
                                </select>
                                <InputError class="mt-1" :message="form.errors.assignee_id" />
                            </div>

                            <div>
                                <InputLabel for="priority" value="Prioritas" />
                                <select id="priority" v-model="form.priority" class="form-select mt-1">
                                    <option v-for="p in priorities" :key="p" :value="p">{{ p }}</option>
                                </select>
                                <InputError class="mt-1" :message="form.errors.priority" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="deadline" value="Deadline (opsional)" />
                            <TextInput id="deadline" type="date" v-model="form.deadline" class="mt-1 block w-full sm:w-64" />
                            <InputError class="mt-1" :message="form.errors.deadline" />
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <Link :href="route('tasks.show', task.id)"><SecondaryButton type="button">Batal</SecondaryButton></Link>
                            <PrimaryButton :disabled="form.processing">Simpan Perubahan</PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
