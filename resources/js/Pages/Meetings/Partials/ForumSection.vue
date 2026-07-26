<script setup>
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    meeting: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const currentUserId = () => page.props.auth.user.id;
const isSuperadmin = () => page.props.auth.user.role === 'superadmin';
const canManage = (comment) => comment.user_id === currentUserId() || isSuperadmin();

const newCommentForm = useForm({ body: '' });
const submitNewComment = () => {
    newCommentForm.post(route('meetings.comments.store', props.meeting.id), {
        preserveScroll: true,
        onSuccess: () => newCommentForm.reset(),
    });
};

const replyingTo = ref(null);
const replyForm = useForm({ body: '', parent_id: null });
const startReply = (commentId) => {
    replyingTo.value = commentId;
    replyForm.reset();
    replyForm.parent_id = commentId;
};
const cancelReply = () => {
    replyingTo.value = null;
};
const submitReply = () => {
    replyForm.post(route('meetings.comments.store', props.meeting.id), {
        preserveScroll: true,
        onSuccess: () => {
            replyForm.reset();
            replyingTo.value = null;
        },
    });
};

const editingId = ref(null);
const editForm = useForm({ body: '' });
const startEdit = (comment) => {
    editingId.value = comment.id;
    editForm.body = comment.body;
};
const cancelEdit = () => {
    editingId.value = null;
};
const submitEdit = (comment) => {
    editForm.put(route('comments.update', comment.id), {
        preserveScroll: true,
        onSuccess: () => (editingId.value = null),
    });
};

const deleteComment = (comment) => {
    if (confirm('Hapus komentar ini?')) {
        router.delete(route('comments.destroy', comment.id), { preserveScroll: true });
    }
};

const formatDateTime = (value) => {
    return new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
};
</script>

<template>
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
        <h3 class="text-lg font-semibold mb-4 text-gray-900">Forum Diskusi</h3>

        <form @submit.prevent="submitNewComment" class="mb-6">
            <textarea
                v-model="newCommentForm.body"
                rows="3"
                placeholder="Tulis komentar tentang rapat ini..."
                class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
            ></textarea>
            <InputError class="mt-2" :message="newCommentForm.errors.body" />
            <div class="mt-2 flex justify-end">
                <PrimaryButton :disabled="newCommentForm.processing || !newCommentForm.body.trim()">Kirim Komentar</PrimaryButton>
            </div>
        </form>

        <p v-if="!meeting.comments?.length" class="text-sm text-gray-500">Belum ada komentar. Jadilah yang pertama berdiskusi.</p>

        <div v-else class="space-y-5">
            <div v-for="comment in meeting.comments" :key="comment.id" class="border-b border-gray-100 pb-4 last:border-b-0">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <span class="text-sm font-semibold text-gray-900">{{ comment.user?.name || 'Pengguna dihapus' }}</span>
                        <span class="text-xs text-gray-400 ml-2">{{ formatDateTime(comment.created_at) }}</span>
                        <span v-if="comment.created_at !== comment.updated_at" class="text-xs text-gray-400 italic"> (diedit)</span>
                    </div>
                </div>

                <div v-if="editingId === comment.id" class="mt-2">
                    <textarea v-model="editForm.body" rows="2" class="block w-full text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                    <InputError class="mt-1" :message="editForm.errors.body" />
                    <div class="mt-2 flex gap-2">
                        <PrimaryButton :disabled="editForm.processing" @click="submitEdit(comment)">Simpan</PrimaryButton>
                        <SecondaryButton @click="cancelEdit">Batal</SecondaryButton>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-700 mt-1 whitespace-pre-wrap">{{ comment.body }}</p>

                <div class="mt-2 flex gap-3 text-xs">
                    <button @click="startReply(comment.id)" class="text-indigo-600 hover:text-indigo-800 font-medium">Balas</button>
                    <template v-if="canManage(comment) && editingId !== comment.id">
                        <button @click="startEdit(comment)" class="text-gray-500 hover:text-gray-700">Edit</button>
                        <button @click="deleteComment(comment)" class="text-red-500 hover:text-red-700">Hapus</button>
                    </template>
                </div>

                <form v-if="replyingTo === comment.id" @submit.prevent="submitReply" class="mt-3 ml-6">
                    <textarea v-model="replyForm.body" rows="2" placeholder="Tulis balasan..." class="block w-full text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                    <InputError class="mt-1" :message="replyForm.errors.body" />
                    <div class="mt-2 flex gap-2">
                        <PrimaryButton :disabled="replyForm.processing || !replyForm.body.trim()">Kirim Balasan</PrimaryButton>
                        <SecondaryButton @click="cancelReply">Batal</SecondaryButton>
                    </div>
                </form>

                <div v-if="comment.replies?.length" class="mt-3 ml-6 space-y-3">
                    <div v-for="reply in comment.replies" :key="reply.id" class="border-l-2 border-gray-100 pl-3">
                        <div>
                            <span class="text-sm font-semibold text-gray-900">{{ reply.user?.name || 'Pengguna dihapus' }}</span>
                            <span class="text-xs text-gray-400 ml-2">{{ formatDateTime(reply.created_at) }}</span>
                        </div>

                        <div v-if="editingId === reply.id" class="mt-1">
                            <textarea v-model="editForm.body" rows="2" class="block w-full text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                            <div class="mt-2 flex gap-2">
                                <PrimaryButton :disabled="editForm.processing" @click="submitEdit(reply)">Simpan</PrimaryButton>
                                <SecondaryButton @click="cancelEdit">Batal</SecondaryButton>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-700 mt-1 whitespace-pre-wrap">{{ reply.body }}</p>

                        <div v-if="canManage(reply) && editingId !== reply.id" class="mt-1 flex gap-3 text-xs">
                            <button @click="startEdit(reply)" class="text-gray-500 hover:text-gray-700">Edit</button>
                            <button @click="deleteComment(reply)" class="text-red-500 hover:text-red-700">Hapus</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
