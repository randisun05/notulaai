<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { MicrophoneIcon, SparklesIcon, UsersIcon, PaperAirplaneIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    laravelVersion: String,
    phpVersion: String,
});

const features = [
    {
        icon: MicrophoneIcon,
        title: 'Transkripsi Akurat',
        description: 'Ubah rekaman audio rapat (MP3, WAV, dll) menjadi teks lengkap menggunakan teknologi Speech-to-Text.',
    },
    {
        icon: SparklesIcon,
        title: 'Rangkuman Cerdas (AI)',
        description: 'AI menganalisis transkrip dan membuat rangkuman poin penting serta daftar action item secara otomatis.',
    },
    {
        icon: UsersIcon,
        title: 'Terstruktur per Unit',
        description: 'Bagikan notula secara aman hanya dengan anggota unit/tim Anda. Kelola siapa melihat apa dengan mudah.',
    },
    {
        icon: PaperAirplaneIcon,
        title: 'Notifikasi Email Otomatis',
        description: 'Dapatkan pengingat rapat dan rangkuman notula langsung di email Anda dan tim setelah rapat selesai.',
    },
];

const highlights = [
    'Action item otomatis ter-assign ke penanggung jawab',
    'Approval workflow untuk penyelesaian task',
    'Dashboard & analitik produktivitas tim',
];
</script>

<template>
    <Head title="AI Notula — Ubah Rapat Jadi Aksi" />

    <div class="relative min-h-screen bg-white text-gray-800 overflow-hidden">
        <!-- Glow dekoratif -->
        <div class="pointer-events-none absolute inset-x-0 -top-40 -z-10 transform-gpu overflow-hidden blur-3xl">
            <div class="relative left-1/2 aspect-[1155/678] w-[72rem] -translate-x-1/2 bg-gradient-to-tr from-brand-300 to-brand-100 opacity-40 [clip-path:polygon(74.1%_44.1%,100%_61.6%,97.5%_26.9%,85.5%_0.1%,80.7%_2%,72.5%_32.5%,60.2%_62.4%,52.4%_68.1%,47.5%_58.3%,45.2%_34.5%,27.5%_76.7%,0.1%_64.9%,17.9%_100%,27.6%_76.8%,76.1%_97.7%,74.1%_44.1%)]" />
        </div>

        <header class="relative z-10">
            <nav class="flex items-center justify-between p-6 lg:px-8 max-w-7xl mx-auto" aria-label="Global">
                <div class="flex items-center gap-2">
                    <svg class="h-8 w-auto text-brand-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <span class="font-bold text-xl text-gray-900 tracking-tight">AI Notula</span>
                </div>
                <div>
                    <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="btn-primary">
                        Buka Dashboard
                    </Link>
                    <Link v-else :href="route('login')" class="btn-primary">
                        Masuk
                    </Link>
                </div>
            </nav>
        </header>

        <main class="relative isolate px-6 pt-14 lg:px-8">
            <div class="mx-auto max-w-3xl py-24 sm:py-32">
                <div class="text-center">
                    <span class="badge-blue mb-6">Ditenagai AI &middot; Whisper &amp; Gemini</span>
                    <h1 class="text-4xl font-bold tracking-tight text-gray-900 sm:text-6xl">
                        Ubah Rapat Anda Menjadi <span class="text-brand-600">Aksi Nyata</span>
                    </h1>
                    <p class="mt-6 text-lg leading-8 text-gray-600">
                        AI Notula secara otomatis mentranskripsi, meringkas, dan mengubah hasil rapat menjadi task
                        terlacak. Fokus pada diskusi, biarkan kami yang mencatat dan menindaklanjuti.
                    </p>
                    <div class="mt-10 flex items-center justify-center gap-x-6">
                        <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="btn-primary btn-md">
                            Masuk ke Dashboard
                        </Link>
                        <Link v-else :href="route('login')" class="btn-primary btn-md">
                            Masuk ke Workspace
                        </Link>
                    </div>

                    <ul class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-x-8 gap-y-2 text-sm text-gray-500">
                        <li v-for="item in highlights" :key="item" class="flex items-center gap-1.5">
                            <CheckCircleIcon class="h-4 w-4 text-brand-500 shrink-0" />
                            {{ item }}
                        </li>
                    </ul>
                </div>
            </div>
        </main>

        <section class="bg-gray-50 py-24 sm:py-32 border-y border-gray-100">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="mx-auto max-w-2xl lg:text-center">
                    <h2 class="text-base font-semibold leading-7 text-brand-600">Lebih Produktif</h2>
                    <p class="mt-2 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
                        Semua yang Anda Butuhkan dari Sebuah Rapat
                    </p>
                    <p class="mt-6 text-lg leading-8 text-gray-600">
                        Dari audio mentah menjadi rangkuman terstruktur dan task terlacak, semua dalam hitungan menit.
                    </p>
                </div>
                <div class="mx-auto mt-16 max-w-2xl sm:mt-20 lg:mt-20 lg:max-w-none">
                    <dl class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div v-for="feature in features" :key="feature.title" class="card-padded">
                            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-600 mb-4">
                                <component :is="feature.icon" class="h-6 w-6 text-white" aria-hidden="true" />
                            </div>
                            <dt class="text-base font-semibold leading-7 text-gray-900">{{ feature.title }}</dt>
                            <dd class="mt-2 text-sm leading-6 text-gray-500">{{ feature.description }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        <section class="py-16">
            <div class="mx-auto max-w-4xl px-6 lg:px-8 text-center">
                <p class="text-sm text-gray-500">
                    Akun baru dibuat oleh admin unit Anda — hubungi admin untuk mendapatkan akses ke workspace.
                </p>
            </div>
        </section>

        <footer class="bg-gray-50 border-t border-gray-100">
            <div class="mx-auto max-w-7xl px-6 py-10 lg:px-8">
                <p class="text-center text-xs leading-5 text-gray-500">
                    &copy; {{ new Date().getFullYear() }} AI Notula. Dibuat dengan Laravel &amp; Vue.
                </p>
            </div>
        </footer>
    </div>
</template>
