<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    apiTokens: { type: Array, required: true },
});

const page = usePage();
const plainTextToken = computed(() => page.props.flash?.plainTextToken);
const apiBase = `${window.location.origin}/api/v1`;

const form = useForm({
    name: '',
});

const createToken = () => {
    form.post(route('api-tokens.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const revokeToken = (token) => {
    if (!confirm(`Cabut token "${token.name}"? Aplikasi/integrasi yang memakainya akan berhenti bisa akses API.`)) {
        return;
    }

    form.delete(route('api-tokens.destroy', token.id), { preserveScroll: true });
};

const formattedDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-lg font-medium text-gray-900">API Tokens</h2>

            <p class="mt-1 text-sm text-gray-600">
                Buat token untuk mengakses API aplikasi ini dari luar (integrasi/otomasi). Token bersifat rahasia,
                perlakukan seperti password.
            </p>
        </header>

        <div v-if="plainTextToken" class="bg-green-50 border border-green-300 rounded-md p-4">
            <p class="text-sm text-green-800 font-medium mb-1">Token berhasil dibuat. Salin sekarang — tidak akan ditampilkan lagi:</p>
            <code class="block bg-white border border-green-200 rounded px-3 py-2 text-xs break-all select-all">{{ plainTextToken }}</code>
        </div>

        <details class="rounded-md border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
            <summary class="cursor-pointer font-medium text-gray-900">Cara memakai API</summary>
            <div class="mt-3 space-y-3">
                <p>Kirim token di header <code class="text-xs">Authorization</code>. Token punya akses yang sama dengan akun Anda (rapat &amp; task di unit Anda), hanya baca.</p>
                <pre class="overflow-x-auto rounded bg-white border border-gray-200 p-3 text-xs"><code>curl -H "Authorization: Bearer &lt;token&gt;" {{ apiBase }}/meetings</code></pre>
                <ul class="list-disc ps-5 space-y-0.5 text-xs font-mono">
                    <li>GET /api/v1/me</li>
                    <li>GET /api/v1/meetings?search=&amp;status=&amp;from=&amp;to=</li>
                    <li>GET /api/v1/meetings/{id}?include=transcript</li>
                    <li>GET /api/v1/tasks?status=&amp;assignee_id=&amp;meeting_id=&amp;overdue=1</li>
                    <li>GET /api/v1/tasks/{id}</li>
                </ul>
                <p class="text-xs text-gray-500">Dokumentasi lengkap: <code>docs/api.md</code> di repository. Batas 60 request/menit.</p>
            </div>
        </details>

        <form @submit.prevent="createToken" class="flex items-end gap-3">
            <div class="flex-1 max-w-xs">
                <InputLabel for="token-name" value="Nama Token" />
                <TextInput id="token-name" v-model="form.name" class="mt-1 block w-full" placeholder="mis. Integrasi Zapier" />
                <InputError :message="form.errors.name" class="mt-2" />
            </div>
            <PrimaryButton :disabled="form.processing">Buat Token</PrimaryButton>
        </form>

        <div v-if="apiTokens.length > 0" class="border-t border-gray-200 pt-4">
            <ul class="divide-y divide-gray-200">
                <li v-for="token in apiTokens" :key="token.id" class="py-3 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ token.name }}</p>
                        <p class="text-xs text-gray-500">
                            Dibuat {{ formattedDate(token.created_at) }} &middot;
                            Terakhir dipakai: {{ formattedDate(token.last_used_at) }}
                        </p>
                    </div>
                    <DangerButton @click="revokeToken(token)">Cabut</DangerButton>
                </li>
            </ul>
        </div>
        <p v-else class="text-sm text-gray-400">Belum ada API token.</p>
    </section>
</template>
