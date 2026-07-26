<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const page = usePage();
const flashError = computed(() => page.props.flash?.error);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <h1 class="text-xl font-bold text-gray-900 mb-1">Selamat datang kembali</h1>
        <p class="text-sm text-gray-500 mb-6">Masuk untuk melanjutkan ke workspace rapat Anda.</p>

        <div v-if="status" class="alert-success mb-4">
            {{ status }}
        </div>

        <div v-if="flashError" class="alert-error mb-4">
            {{ flashError }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Password" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="block mt-4">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-gray-600">Ingat saya</span>
                </label>
            </div>

            <div class="flex items-center justify-between mt-6">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm text-brand-600 hover:text-brand-800 font-medium"
                >
                    Lupa password?
                </Link>

                <PrimaryButton class="ms-auto" :class="{ 'opacity-50': form.processing }" :disabled="form.processing">
                    Masuk
                </PrimaryButton>
            </div>
        </form>

        <div class="mt-8">
            <div class="relative">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-200"></div>
                </div>
                <div class="relative flex justify-center text-xs">
                    <span class="px-2 bg-white text-gray-400 uppercase tracking-wider">Atau masuk dengan</span>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3">
                <a :href="route('social.redirect', 'google')" class="btn-secondary">
                    Google
                </a>
                <a :href="route('social.redirect', 'microsoft')" class="btn-secondary">
                    Microsoft
                </a>
            </div>
        </div>
    </GuestLayout>
</template>
