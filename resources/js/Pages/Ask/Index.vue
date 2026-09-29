<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    units: { type: Array, default: () => [] },
});

const question = ref('');
const filters = reactive({ unit_id: '', from: '', to: '' });
const asking = ref(false);
const error = ref('');
// Riwayat tanya-jawab sesi ini (tidak disimpan di server).
const history = ref([]);

const examples = [
    'Apa saja keputusan terkait anggaran dalam 3 bulan terakhir?',
    'Bagaimana perkembangan pembahasan rancangan peraturan dari rapat ke rapat?',
    'Tindak lanjut apa yang masih terbuka dan siapa PIC-nya?',
];

const ask = async (text = null) => {
    const q = (text ?? question.value).trim();
    if (!q || asking.value) return;
    asking.value = true;
    error.value = '';
    try {
        const payload = { question: q, ...Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '')) };
        const { data } = await axios.post(route('ask.store'), payload);
        history.value.unshift({ question: q, ...data });
        question.value = '';
    } catch (e) {
        error.value = e.response?.data?.error || e.response?.data?.message || 'Gagal mendapat jawaban dari AI.';
    } finally {
        asking.value = false;
    }
};

const date = (d) => (d ? new Date(String(d).replace(' ', 'T')).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '');

// Jawaban AI ditampilkan sebagai teks (di-escape); hanya penanda [n] yang dijadikan tautan ke rapatnya.
const escapeHtml = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const renderAnswer = (item) => {
    const byNumber = Object.fromEntries(item.sources.map((s) => [s.n, s]));
    return escapeHtml(item.answer).replace(/\[(\d+)\]/g, (match, n) => {
        const source = byNumber[n];
        if (!source) return match;
        return `<a href="${route('meetings.show', source.id)}" class="text-brand-600 hover:text-brand-800 font-medium" title="${escapeHtml(source.title)} — ${date(source.date)}">[${n}]</a>`;
    });
};
</script>

<template>
    <Head title="Tanya Lintas Rapat" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="page-heading">Tanya Lintas Rapat</h2>
                <p class="page-subheading">
                    Satu pertanyaan untuk semua notula rapat {{ units.length ? 'seluruh unit' : 'unit Anda' }} — jawaban AI disertai rujukan rapat &amp; tanggalnya.
                </p>
            </div>
        </template>

        <div class="page-shell">
            <div class="page-container max-w-4xl">
                <div class="card-padded">
                    <form @submit.prevent="ask()">
                        <textarea v-model="question" rows="3" maxlength="1000" class="form-textarea" placeholder="Contoh: Apa keputusan terakhir soal pembangunan jembatan, dan tindak lanjutnya sudah sampai mana?" @keydown.enter.exact.prevent="ask()"></textarea>
                        <div class="mt-3 flex flex-wrap items-end gap-3">
                            <div v-if="units.length">
                                <label class="form-label">Unit</label>
                                <select v-model="filters.unit_id" class="form-select">
                                    <option value="">Semua unit</option>
                                    <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Rapat dari</label>
                                <input v-model="filters.from" type="date" class="form-input" />
                            </div>
                            <div>
                                <label class="form-label">sampai</label>
                                <input v-model="filters.to" type="date" class="form-input" />
                            </div>
                            <PrimaryButton class="ml-auto" :disabled="asking || !question.trim()">{{ asking ? 'Mencari di notula...' : 'Tanya' }}</PrimaryButton>
                        </div>
                    </form>
                    <p v-if="error" class="mt-3 text-sm text-red-600">{{ error }}</p>

                    <div v-if="!history.length" class="mt-4 flex flex-wrap gap-2">
                        <button v-for="example in examples" :key="example" type="button" class="rounded-full border border-gray-200 px-3 py-1 text-xs text-gray-600 hover:bg-gray-50" @click="ask(example)">
                            {{ example }}
                        </button>
                    </div>
                </div>

                <div v-for="(item, i) in history" :key="history.length - i" class="card-padded mt-6">
                    <p class="text-sm font-semibold text-gray-900">{{ item.question }}</p>
                    <div class="mt-3 whitespace-pre-wrap text-sm leading-relaxed text-gray-800" v-html="renderAnswer(item)"></div>

                    <div v-if="item.sources.length" class="mt-4 border-t border-gray-100 pt-3">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Sumber</div>
                        <ol class="mt-1 space-y-1 text-sm">
                            <li v-for="s in item.sources" :key="s.n" :class="s.cited ? '' : 'opacity-60'">
                                <span class="font-mono text-xs text-gray-400">[{{ s.n }}]</span>
                                <Link :href="route('meetings.show', s.id)" class="text-brand-600 hover:text-brand-800">{{ s.title }}</Link>
                                <span class="text-xs text-gray-500"> · {{ date(s.date) }}<template v-if="s.unit"> · {{ s.unit }}</template></span>
                                <span v-if="!s.cited" class="text-xs text-gray-400"> (dibaca, tidak dirujuk)</span>
                            </li>
                        </ol>
                    </div>
                    <p class="mt-3 text-xs text-gray-400">Jawaban AI dapat keliru — periksa kembali pada notula rapat yang dirujuk.</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
