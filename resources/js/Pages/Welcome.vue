<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { MicrophoneIcon, SparklesIcon, UsersIcon, PaperAirplaneIcon } from '@heroicons/vue/24/outline';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    laravelVersion: String,
    phpVersion: String,
});
</script>

<template>
    <Head title="Selamat Datang di AI-Notula" />

    <div class="relative min-h-screen bg-gray-100 text-gray-800">
        <!-- Latar Belakang Gradien Halus -->
        <div class="absolute inset-x-0 top-0 -z-10 h-full w-full overflow-hidden">
            <svg class="absolute left-[max(50%,25rem)] top-0 h-[64rem] w-[128rem] -translate-x-1/2 stroke-gray-200 [mask-image:radial-gradient(64rem_64rem_at_top,white,transparent)]" aria-hidden="true">
                <defs>
                    <pattern id="e813992c-7d03-40a1-a1c1-4b5b707010f3" width="200" height="200" x="50%" y="-1" patternUnits="userSpaceOnUse">
                        <path d="M100 200V.5M.5 .5H200" fill="none" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" stroke-width="0" fill="url(#e813992c-7d03-40a1-a1c1-4b5b707010f3)" />
            </svg>
        </div>

        <!-- Header / Navigasi -->
        <header class="absolute inset-x-0 top-0 z-50">
            <nav class="flex items-center justify-between p-6 lg:px-8 max-w-7xl mx-auto" aria-label="Global">
                <div class="flex lg:flex-1">
                    <a href="#" class="-m-1.5 p-1.5 flex items-center space-x-2">
                        <svg class="h-8 w-auto text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                           <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        <span class="font-bold text-xl text-gray-900">AI-Notula</span>
                    </a>
                </div>
                <div class="lg:flex lg:flex-1 lg:justify-end">
                     <div v-if="$page.props.auth.user">
                        <Link :href="route('dashboard')" class="text-sm font-semibold leading-6 text-gray-900 hover:text-gray-700">
                            Dashboard <span aria-hidden="true">&rarr;</span>
                        </Link>
                    </div>
                    <template v-else>
                        <Link :href="route('login')" class="text-sm font-semibold leading-6 text-gray-900 hover:text-gray-700">
                            Log in <span aria-hidden="true">&rarr;</span>
                        </Link>
                        <Link v-if="canRegister" :href="route('register')" class="ml-6 rounded-md bg-blue-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                            Daftar
                        </Link>
                    </template>
                </div>
            </nav>
        </header>

        <!-- Hero Section -->
        <main class="relative isolate px-6 pt-14 lg:px-8">
            <div class="mx-auto max-w-3xl py-32 sm:py-48 lg:py-56">
                <div class="text-center">
                    <h1 class="text-4xl font-bold tracking-tight text-gray-900 sm:text-6xl">
                        Ubah Rapat Anda Menjadi Aksi Nyata
                    </h1>
                    <p class="mt-6 text-lg leading-8 text-gray-600">
                        AI-Notula secara otomatis mentranskripsi, meringkas, dan mengatur hasil rapat Anda. Fokus pada diskusi, biarkan kami yang mencatat.
                    </p>
                    <div class="mt-10 flex items-center justify-center gap-x-6">
                        <Link v-if="!$page.props.auth.user && canRegister" :href="route('register')" class="rounded-md bg-blue-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                            Mulai Gratis
                        </Link>
                        <Link v-else :href="route('dashboard')" class="rounded-md bg-blue-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                            Masuk ke Dashboard
                        </Link>
                    </div>
                </div>
            </div>
        </main>

        <!-- Features Section -->
        <section class="bg-white py-24 sm:py-32">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="mx-auto max-w-2xl lg:text-center">
                    <h2 class="text-base font-semibold leading-7 text-blue-600">Lebih Produktif</h2>
                    <p class="mt-2 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
                        Semua yang Anda Butuhkan dari Sebuah Rapat
                    </p>
                    <p class="mt-6 text-lg leading-8 text-gray-600">
                        Dari audio mentah menjadi rangkuman terstruktur, semua dalam hitungan menit.
                    </p>
                </div>
                <div class="mx-auto mt-16 max-w-2xl sm:mt-20 lg:mt-24 lg:max-w-4xl">
                    <dl class="grid max-w-xl grid-cols-1 gap-x-8 gap-y-10 lg:max-w-none lg:grid-cols-2 lg:gap-y-16">
                        <div class="relative ps-16">
                            <dt class="text-base font-semibold leading-7 text-gray-900">
                                <div class="absolute left-0 top-0 flex h-10 w-10 items-center justify-center rounded-lg bg-blue-600">
                                    <MicrophoneIcon class="h-6 w-6 text-white" aria-hidden="true" />
                                Daftar
                                </div>
                                Transkripsi Akurat
                            </dt>
                            <dd class="mt-2 text-base leading-7 text-gray-600">Ubah rekaman audio rapat (MP3, WAV, dll) menjadi teks lengkap menggunakan teknologi Speech-to-Text yang canggih.</dd>
                        </div>
                        <div class="relative ps-16">
                            <dt class="text-base font-semibold leading-7 text-gray-900">
                                <div class="absolute left-0 top-0 flex h-10 w-10 items-center justify-center rounded-lg bg-blue-600">
                                    <SparklesIcon class="h-6 w-6 text-white" aria-hidden="true" />
                                </div>
                                Rangkuman Cerdas (AI)
                            </dt>
                            <dd class="mt-2 text-base leading-7 text-gray-600">AI kami akan menganalisis transkrip dan membuat rangkuman poin-poin penting serta daftar *action items* secara otomatis.</dd>
                        </div>
                        <div class="relative ps-16">
                            <dt class="text-base font-semibold leading-7 text-gray-900">
                                <div class="absolute left-0 top-0 flex h-10 w-10 items-center justify-center rounded-lg bg-blue-600">
                                    <UsersIcon class="h-6 w-6 text-white" aria-hidden="true" />
                                </div>
                                Terstruktur per Unit
                            </dt>
                            <dd class="mt-2 text-base leading-7 text-gray-600">Bagikan notulen secara aman hanya dengan anggota unit atau tim Anda. Kelola siapa melihat apa dengan mudah.</dd>
                        </div>
                        <div class="relative ps-16">
                            <dt class="text-base font-semibold leading-7 text-gray-900">
                                <div class="absolute left-0 top-0 flex h-10 w-10 items-center justify-center rounded-lg bg-blue-600">
                                    <PaperAirplaneIcon class="h-6 w-6 text-white" aria-hidden="true" />
                                </div>
                                Notifikasi Email Otomatis
                            </dt>
                            <dd class="mt-2 text-base leading-7 text-gray-600">Dapatkan pengingat rapat dan terima hasil rangkuman notulen langsung di email Anda dan tim Anda setelah rapat selesai.</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-gray-100">
            <div class="mx-auto max-w-7xl px-6 py-12 lg:px-8">
                <p class="text-center text-xs leading-5 text-gray-500">
                    &copy; {{ new Date().getFullYear() }} AI-Notula. Dibuat dengan Laravel & Vue.
                </p>
            </div>
        </footer>
    </div>
</template>
