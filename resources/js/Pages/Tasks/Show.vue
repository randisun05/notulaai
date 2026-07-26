<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    task: { type: Object, required: true },
    statuses: { type: Array, required: true },
    unitUsers: { type: Array, default: () => [] },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const updateStatus = (status) => {
    router.patch(route('tasks.update-status', props.task.id), { status }, {
        preserveScroll: true,
    });
};

const disposeForm = useForm({ to_user_id: '', note: '' });
const submitDisposition = () => {
    disposeForm.post(route('tasks.dispositions.store', props.task.id), {
        preserveScroll: true,
        onSuccess: () => disposeForm.reset(),
    });
};

const formattedDate = (dateString) => {
    if (!dateString) return 'Belum ditentukan';
    return new Date(dateString).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
};

const formattedDateTime = (value) => {
    return new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
};

const priorityColor = (priority) => ({
    Urgent: 'bg-red-100 text-red-700',
    High: 'bg-orange-100 text-orange-700',
    Medium: 'bg-blue-100 text-blue-700',
    Low: 'bg-gray-100 text-gray-600',
}[priority] || 'bg-gray-100 text-gray-600');
</script>

<template>
    <Head :title="task.title" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ task.title }}</h2>
        </template>

        <div class="py-12">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <div v-if="flashSuccess" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    {{ flashSuccess }}
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-4 text-gray-900">Detail Task</h3>

                        <dl class="space-y-4">
                            <div v-if="task.description">
                                <dt class="text-sm font-medium text-gray-500">Deskripsi</dt>
                                <dd class="mt-1 text-sm text-gray-800 whitespace-pre-wrap">{{ task.description }}</dd>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">PIC</dt>
                                    <dd class="mt-1 text-sm text-gray-800">{{ task.assignee ? task.assignee.name : (task.assignee_name || 'Belum ditentukan') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Deadline</dt>
                                    <dd class="mt-1 text-sm text-gray-800">{{ formattedDate(task.deadline) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Prioritas</dt>
                                    <dd class="mt-1">
                                        <span :class="['text-xs px-2 py-0.5 rounded-full', priorityColor(task.priority)]">{{ task.priority }}</span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Unit</dt>
                                    <dd class="mt-1 text-sm text-gray-800">{{ task.unit?.name || '-' }}</dd>
                                </div>
                            </div>

                            <div v-if="task.meeting">
                                <dt class="text-sm font-medium text-gray-500">Rapat Sumber</dt>
                                <dd class="mt-1 text-sm">
                                    <Link :href="route('meetings.show', task.meeting.id)" class="text-indigo-600 hover:text-indigo-900">{{ task.meeting.title }}</Link>
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Dibuat oleh</dt>
                                <dd class="mt-1 text-sm text-gray-800">{{ task.creator?.name || 'Sistem (AI)' }}</dd>
                            </div>
                        </dl>

                        <div class="mt-6 pt-6 border-t border-gray-100">
                            <label class="text-sm font-medium text-gray-500">Status</label>
                            <select :value="task.status" @change="updateStatus($event.target.value)" class="mt-1 block w-full sm:w-64 border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500">
                                <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
                            </select>
                        </div>

                        <div class="mt-6 pt-6 border-t border-gray-100">
                            <h4 class="text-sm font-medium text-gray-700 mb-2">Disposisikan Task</h4>
                            <form @submit.prevent="submitDisposition" class="flex flex-wrap items-start gap-2">
                                <div>
                                    <select v-model="disposeForm.to_user_id" class="block w-56 border-gray-300 rounded-md text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="" disabled>Pilih penerima...</option>
                                        <option v-for="u in unitUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                                    </select>
                                    <InputError class="mt-1" :message="disposeForm.errors.to_user_id" />
                                </div>
                                <div class="flex-1 min-w-[12rem]">
                                    <input v-model="disposeForm.note" type="text" placeholder="Catatan (opsional)" class="block w-full border-gray-300 rounded-md text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    <InputError class="mt-1" :message="disposeForm.errors.note" />
                                </div>
                                <PrimaryButton :disabled="disposeForm.processing || !disposeForm.to_user_id">Disposisikan</PrimaryButton>
                            </form>

                            <div v-if="task.dispositions?.length" class="mt-4 space-y-2">
                                <div v-for="d in task.dispositions" :key="d.id" class="text-xs text-gray-500 border-l-2 border-gray-200 pl-2">
                                    <span class="font-medium text-gray-700">{{ d.from_user?.name || 'Sistem' }}</span> &rarr;
                                    <span class="font-medium text-gray-700">{{ d.to_user?.name }}</span>
                                    <span v-if="d.note"> &middot; "{{ d.note }}"</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-4 text-gray-900">Riwayat Task</h3>
                        <p v-if="!task.activities?.length" class="text-sm text-gray-500">Belum ada riwayat.</p>
                        <ol v-else class="space-y-4">
                            <li v-for="activity in task.activities" :key="activity.id">
                                <p class="text-sm text-gray-700">{{ activity.description }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ formattedDateTime(activity.created_at) }}</p>
                            </li>
                        </ol>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
