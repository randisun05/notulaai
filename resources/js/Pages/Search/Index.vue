<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { SEARCH_GROUPS, SEARCH_MIN_LENGTH } from '@/Components/Search/searchGroups';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    q: { type: String, default: null },
    results: { type: Object, default: null },
});

const term = ref(props.q || '');
const total = computed(() => (props.results ? SEARCH_GROUPS.reduce((n, g) => n + (props.results[g.key]?.length || 0), 0) : 0));

const submit = () => {
    router.get(route('search.index'), { q: term.value.trim() || undefined }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head :title="q ? `Cari: ${q}` : 'Pencarian'" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="page-heading">Pencarian</h2>
        </template>

        <div class="page-shell">
            <div class="page-container">
                <form @submit.prevent="submit" class="card-padded flex gap-3" role="search">
                    <label for="search-page-input" class="sr-only">Kata kunci</label>
                    <input id="search-page-input" v-model="term" type="search" class="form-input" placeholder="Cari rapat, task, atau komentar…" autofocus />
                    <button type="submit" class="btn-primary">Cari</button>
                </form>

                <p v-if="!q" class="empty-state">Masukkan minimal {{ SEARCH_MIN_LENGTH }} huruf untuk mencari.</p>
                <p v-else-if="total === 0" class="empty-state">Tidak ada hasil untuk “{{ q }}”.</p>

                <template v-else>
                    <section v-for="group in SEARCH_GROUPS" :key="group.key" v-show="results[group.key]?.length" class="card-padded">
                        <h3 class="card-title mb-4 flex items-center gap-2">
                            <component :is="group.icon" class="h-5 w-5 text-gray-400" aria-hidden="true" />
                            {{ group.label }}
                            <span class="badge-gray">{{ results[group.key].length }}</span>
                        </h3>
                        <ul class="divide-y divide-gray-100">
                            <li v-for="item in results[group.key]" :key="item.id">
                                <Link :href="item.url" class="block py-3 hover:bg-gray-50 -mx-3 px-3 rounded-lg transition">
                                    <span class="block text-sm font-medium text-brand-700">{{ item.title }}</span>
                                    <span v-if="item.subtitle" class="block text-xs text-gray-500">{{ item.subtitle }}</span>
                                    <span v-if="item.snippet" class="block text-sm text-gray-600 mt-1">{{ item.snippet }}</span>
                                </Link>
                            </li>
                        </ul>
                        <p v-if="results[group.key].length >= 20" class="mt-3 text-xs text-gray-400">Menampilkan 20 hasil teratas. Persempit kata kunci untuk hasil yang lebih spesifik.</p>
                    </section>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
