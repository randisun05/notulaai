<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    setting: { type: Object, required: true },
    textProviders: { type: Array, required: true },
    transcriptionProviders: { type: Array, required: true },
    ocrProviders: { type: Array, required: true },
});

const timezones = [
    'Asia/Jakarta',
    'Asia/Makassar',
    'Asia/Jayapura',
    'UTC',
];

const form = useForm({
    company_name: props.setting.company_name,
    company_address: props.setting.company_address || '',
    company_logo: null,
    ai_text_provider: props.setting.ai_text_provider,
    ai_transcription_provider: props.setting.ai_transcription_provider,
    ai_ocr_provider: props.setting.ai_ocr_provider,
    timezone: props.setting.timezone,
});

const currentLogoUrl = computed(() => {
    return props.setting.company_logo_path ? `/storage/${props.setting.company_logo_path}` : null;
});

const submit = () => {
    form.put(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('company_logo');
        },
    });
};
</script>

<template>
    <Head title="Pengaturan Aplikasi" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="page-heading">Pengaturan Aplikasi</h2>
        </template>

        <div class="page-shell">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <div v-if="$page.props.flash?.success" class="alert-success">
                    {{ $page.props.flash.success }}
                </div>

                <form @submit.prevent="submit" class="space-y-6">

                    <div class="card">
                        <div class="card-padded space-y-4">
                            <h3 class="card-title">Perusahaan / Instansi</h3>

                            <div>
                                <InputLabel for="company_name" value="Nama Perusahaan" />
                                <TextInput id="company_name" type="text" class="mt-1 block w-full" v-model="form.company_name" required />
                                <InputError class="mt-2" :message="form.errors.company_name" />
                            </div>

                            <div>
                                <InputLabel for="company_address" value="Alamat (Opsional)" />
                                <textarea id="company_address" rows="3" class="form-input mt-1 block w-full" v-model="form.company_address" placeholder="Alamat, telepon, laman — tiap baris tampil apa adanya di kop notula"></textarea>
                                <InputError class="mt-2" :message="form.errors.company_address" />
                            </div>

                            <div>
                                <InputLabel for="company_logo" value="Logo (Opsional)" />
                                <img v-if="currentLogoUrl" :src="currentLogoUrl" alt="Logo saat ini" class="h-12 mt-1 mb-2 object-contain" />
                                <input
                                    id="company_logo"
                                    type="file"
                                    accept="image/*"
                                    class="mt-1 block w-full text-sm text-gray-600"
                                    @input="form.company_logo = $event.target.files[0]"
                                />
                                <InputError class="mt-2" :message="form.errors.company_logo" />
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-padded space-y-4">
                            <h3 class="card-title">AI Provider</h3>
                            <p class="text-sm text-gray-500">Menentukan provider AI yang dipakai untuk transkripsi dan ringkasan notula.</p>

                            <div>
                                <InputLabel for="ai_text_provider" value="Provider Ringkasan (Teks)" />
                                <select id="ai_text_provider" v-model="form.ai_text_provider" class="form-select mt-1">
                                    <option v-for="p in textProviders" :key="p" :value="p">{{ p }}</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.ai_text_provider" />
                            </div>

                            <div>
                                <InputLabel for="ai_transcription_provider" value="Provider Transkripsi (Audio)" />
                                <select id="ai_transcription_provider" v-model="form.ai_transcription_provider" class="form-select mt-1">
                                    <option v-for="p in transcriptionProviders" :key="p" :value="p">{{ p }}</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.ai_transcription_provider" />
                            </div>

                            <div>
                                <InputLabel for="ai_ocr_provider" value="Provider OCR (Gambar)" />
                                <select id="ai_ocr_provider" v-model="form.ai_ocr_provider" class="form-select mt-1">
                                    <option v-for="p in ocrProviders" :key="p" :value="p">{{ p }}</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.ai_ocr_provider" />
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-padded space-y-4">
                            <h3 class="card-title">Zona Waktu</h3>

                            <div>
                                <InputLabel for="timezone" value="Timezone" />
                                <select id="timezone" v-model="form.timezone" class="form-select mt-1">
                                    <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.timezone" />
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            Simpan Pengaturan
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
