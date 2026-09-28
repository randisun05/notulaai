<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    token: { type: String, required: true },
    meeting: { type: Object, required: true },
    open: { type: Boolean, default: true },
    user: { type: Object, default: null },
    checkedInAt: { type: String, default: null },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

// Tamu mengetik ulang di rapat berikutnya — ingat isian terakhir di perangkat ini.
const remembered = (() => {
    try { return JSON.parse(localStorage.getItem('notula-hadir') ?? '{}'); } catch { return {}; }
})();
const form = useForm({
    name: remembered.name ?? '',
    position: remembered.position ?? '',
    organization: remembered.organization ?? '',
});

const submit = () => {
    if (!props.user) {
        try { localStorage.setItem('notula-hadir', JSON.stringify(form.data())); } catch { /* mode privat */ }
    }
    form.post(route('attendance.check-in.store', props.token), { preserveScroll: true });
};

const dateLabel = computed(() => new Date(props.meeting.date.replace(' ', 'T'))
    .toLocaleString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }));
const checkedInLabel = computed(() => props.checkedInAt
    ? new Date(props.checkedInAt).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : null);
</script>

<template>
    <Head title="Daftar Hadir" />

    <GuestLayout>
        <p class="text-xs font-semibold uppercase tracking-wide text-brand-600">Daftar Hadir</p>
        <h1 class="mt-1 text-lg font-semibold text-gray-900">{{ meeting.title }}</h1>
        <p class="text-sm text-gray-600">{{ dateLabel }}<span v-if="meeting.unit"> · {{ meeting.unit }}</span></p>

        <div v-if="flash.success" class="mt-6 rounded-lg border border-green-200 bg-green-50 p-4 text-green-800">
            <p class="text-2xl">&#10003;</p>
            <p class="mt-1 font-medium">{{ flash.success }}</p>
        </div>
        <div v-else-if="flash.error" class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ flash.error }}</div>

        <template v-if="!flash.success">
            <div v-if="!open" class="mt-6 rounded-lg bg-gray-50 p-4 text-sm text-gray-700">
                Daftar hadir rapat ini sudah ditutup. Hubungi notulen rapat bila Anda hadir namun belum tercatat.
            </div>

            <!-- Pegawai yang sudah login -->
            <div v-else-if="user" class="mt-6 space-y-4">
                <p v-if="checkedInLabel" class="rounded-lg bg-green-50 p-4 text-sm text-green-800">
                    Kehadiran Anda sudah tercatat pukul {{ checkedInLabel }} WIB.
                </p>
                <template v-else>
                    <p class="text-sm text-gray-700">Catat kehadiran sebagai <strong>{{ user.name }}</strong><span v-if="user.unit"> ({{ user.unit }})</span>.</p>
                    <PrimaryButton class="w-full justify-center py-3" :disabled="form.processing" @click="submit">Saya Hadir</PrimaryButton>
                </template>
            </div>

            <!-- Tamu tanpa akun -->
            <form v-else class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <InputLabel for="name" value="Nama lengkap" />
                    <TextInput id="name" v-model="form.name" class="mt-1 w-full" autocomplete="name" required autofocus />
                    <InputError class="mt-1" :message="form.errors.name" />
                </div>
                <div>
                    <InputLabel for="position" value="Jabatan" />
                    <TextInput id="position" v-model="form.position" class="mt-1 w-full" placeholder="mis. Kepala Bagian Umum" />
                </div>
                <div>
                    <InputLabel for="organization" value="Unit / Instansi" />
                    <TextInput id="organization" v-model="form.organization" class="mt-1 w-full" placeholder="mis. Dinas Pendidikan Kota" autocomplete="organization" />
                </div>
                <PrimaryButton class="w-full justify-center py-3" :disabled="form.processing">Saya Hadir</PrimaryButton>
                <p class="text-center text-xs text-gray-500">
                    Pegawai dengan akun? <a :href="route('login')" class="underline">Masuk dulu</a> supaya tercatat otomatis.
                </p>
            </form>
        </template>
    </GuestLayout>
</template>
