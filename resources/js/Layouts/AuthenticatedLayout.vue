<script setup>
import { ref, computed } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);

const page = usePage();
const userInitials = computed(() => {
    const name = page.props.auth.user.name || '';
    return name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
});
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <nav class="bg-white/90 backdrop-blur border-b border-gray-100 sticky top-0 z-30">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="shrink-0 flex items-center gap-2">
                            <Link :href="route('dashboard')" class="flex items-center gap-2">
                                <ApplicationLogo class="block h-8 w-auto fill-current text-brand-600" />
                                <span class="hidden sm:block font-bold text-gray-900 tracking-tight">AI Notula</span>
                            </Link>
                        </div>

                        <div class="hidden space-x-6 sm:-my-px sm:ms-10 sm:flex">
                            <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                Dashboard
                            </NavLink>
                            <NavLink :href="route('meetings.index')" :active="route().current('meetings.*')">
                                Rapat
                            </NavLink>
                            <NavLink :href="route('tasks.index')" :active="route().current('tasks.*')">
                                Task
                            </NavLink>
                            <NavLink :href="route('decisions.index')" :active="route().current('decisions.*')">
                                Keputusan
                            </NavLink>
                            <NavLink :href="route('ask.index')" :active="route().current('ask.*')">
                                Tanya AI
                            </NavLink>
                            <NavLink :href="route('analytics.productivity')" :active="route().current('analytics.*')">
                                Analitik
                            </NavLink>

                            <template v-if="$page.props.auth.user.role === 'superadmin'">
                                <NavLink :href="route('admin.units.index')" :active="route().current('admin.units.*')">
                                    Unit
                                </NavLink>
                                <NavLink :href="route('admin.users.index')" :active="route().current('admin.users.*')">
                                    User
                                </NavLink>
                                <NavLink :href="route('admin.settings.edit')" :active="route().current('admin.settings.*')">
                                    Pengaturan
                                </NavLink>
                                <NavLink :href="route('admin.audit-logs.index')" :active="route().current('admin.audit-logs.*')">
                                    Audit Log
                                </NavLink>
                                <NavLink :href="route('admin.webhooks.index')" :active="route().current('admin.webhooks.*')">
                                    Webhooks
                                </NavLink>
                            </template>
                        </div>
                    </div>

                    <div class="hidden sm:flex sm:items-center sm:ms-6">
                        <div class="ms-3 relative">
                            <Dropdown align="right" width="48">
                                <template #trigger>
                                    <button
                                        type="button"
                                        class="flex items-center gap-2 pl-1.5 pr-3 py-1.5 rounded-full border border-gray-200 bg-white hover:border-brand-300 hover:shadow-sm text-sm font-medium text-gray-600 transition"
                                    >
                                        <span class="flex items-center justify-center h-7 w-7 rounded-full bg-brand-600 text-white text-xs font-semibold">
                                            {{ userInitials }}
                                        </span>
                                        {{ $page.props.auth.user.name }}
                                        <svg class="-me-0.5 h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </template>

                                <template #content>
                                    <DropdownLink :href="route('profile.edit')"> Profile </DropdownLink>
                                    <DropdownLink :href="route('logout')" method="post" as="button">
                                        Log Out
                                    </DropdownLink>
                                </template>
                            </Dropdown>
                        </div>
                    </div>

                    <div class="-me-2 flex items-center sm:hidden">
                        <button
                            @click="showingNavigationDropdown = !showingNavigationDropdown"
                            class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out"
                        >
                            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path
                                    :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }"
                                    stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"
                                />
                                <path
                                    :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                                    stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden border-t border-gray-100">
                <div class="pt-2 pb-3 space-y-1">
                    <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                        Dashboard
                    </ResponsiveNavLink>
                    <ResponsiveNavLink :href="route('meetings.index')" :active="route().current('meetings.*')">
                        Rapat
                    </ResponsiveNavLink>
                    <ResponsiveNavLink :href="route('tasks.index')" :active="route().current('tasks.*')">
                        Task
                    </ResponsiveNavLink>
                    <ResponsiveNavLink :href="route('decisions.index')" :active="route().current('decisions.*')">
                        Keputusan
                    </ResponsiveNavLink>
                    <ResponsiveNavLink :href="route('ask.index')" :active="route().current('ask.*')">
                        Tanya AI
                    </ResponsiveNavLink>
                    <ResponsiveNavLink :href="route('analytics.productivity')" :active="route().current('analytics.*')">
                        Analitik
                    </ResponsiveNavLink>
                </div>

                <template v-if="$page.props.auth.user.role === 'superadmin'">
                    <div class="pt-4 pb-1 border-t border-gray-200">
                        <div class="px-4">
                            <div class="font-semibold text-xs uppercase tracking-wider text-gray-400">Panel Admin</div>
                        </div>
                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('admin.units.index')" :active="route().current('admin.units.*')">
                                Manajemen Unit
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('admin.users.index')" :active="route().current('admin.users.*')">
                                Manajemen User
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('admin.settings.edit')" :active="route().current('admin.settings.*')">
                                Pengaturan
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('admin.audit-logs.index')" :active="route().current('admin.audit-logs.*')">
                                Audit Log
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('admin.webhooks.index')" :active="route().current('admin.webhooks.*')">
                                Webhooks
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </template>

                <div class="pt-4 pb-3 border-t border-gray-200">
                    <div class="px-4 flex items-center gap-3">
                        <span class="flex items-center justify-center h-9 w-9 rounded-full bg-brand-600 text-white text-sm font-semibold">
                            {{ userInitials }}
                        </span>
                        <div>
                            <div class="font-semibold text-base text-gray-800">{{ $page.props.auth.user.name }}</div>
                            <div class="text-sm text-gray-500">{{ $page.props.auth.user.email }}</div>
                        </div>
                    </div>

                    <div class="mt-3 space-y-1">
                        <ResponsiveNavLink :href="route('profile.edit')"> Profile </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('logout')" method="post" as="button">
                            Log Out
                        </ResponsiveNavLink>
                    </div>
                </div>
            </div>
        </nav>

        <header class="bg-white border-b border-gray-100" v-if="$slots.header">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <main>
            <slot />
        </main>
    </div>
</template>
