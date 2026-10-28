<script setup>
import { ref, computed } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import NotificationBell from '@/Components/Notifications/NotificationBell.vue';
import PageSkeleton from '@/Components/Skeleton/PageSkeleton.vue';
import { useNavigationSkeleton } from '@/Composables/useNavigationSkeleton';
import { Link, usePage } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
const navigationSkeleton = useNavigationSkeleton();

// Admin area, grouped in one dropdown so the top nav stays on one line.
// `roles` mirrors the route gates: access-unit-admin (admin + superadmin)
// vs access-admin-panel (superadmin only). The server enforces both.
const ADMIN_LINKS = [
    { label: 'Unit', route: 'admin.units.index', active: 'admin.units.*', roles: ['superadmin'] },
    { label: 'User', route: 'admin.users.index', active: 'admin.users.*', roles: ['admin', 'superadmin'] },
    { label: 'Pengaturan', route: 'admin.settings.edit', active: 'admin.settings.*', roles: ['superadmin'] },
    { label: 'Webhooks', route: 'admin.webhooks.index', active: 'admin.webhooks.*', roles: ['admin', 'superadmin'] },
    { label: 'Audit Log', route: 'admin.audit-logs.index', active: 'admin.audit-logs.*', roles: ['superadmin'] },
    { label: 'Pemakaian AI', route: 'admin.ai-usage.index', active: 'admin.ai-usage.*', roles: ['superadmin'] },
];
const adminLinks = computed(() => ADMIN_LINKS.filter((link) => link.roles.includes(page.props.auth.user.role)));

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
                            <NavLink :href="route('analytics.productivity')" :active="route().current('analytics.*')">
                                Analitik
                            </NavLink>

                            <Dropdown v-if="adminLinks.length" align="left" width="48">
                                <template #trigger>
                                    <button
                                        type="button"
                                        class="h-16 gap-1"
                                        :class="route().current('admin.*') ? 'nav-link-active' : 'nav-link-inactive'"
                                    >
                                        Admin
                                        <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </template>

                                <template #content>
                                    <DropdownLink
                                        v-for="link in adminLinks"
                                        :key="link.route"
                                        :href="route(link.route)"
                                        :class="route().current(link.active) ? 'bg-brand-50 text-brand-700 font-semibold' : ''"
                                    >
                                        {{ link.label }}
                                    </DropdownLink>
                                </template>
                            </Dropdown>
                        </div>
                    </div>

                    <div class="hidden sm:flex sm:items-center sm:ms-6 gap-2">
                        <NotificationBell />
                        <div class="ms-1 relative">
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

                    <div class="-me-2 flex items-center gap-1 sm:hidden">
                        <NotificationBell />
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
                    <ResponsiveNavLink :href="route('analytics.productivity')" :active="route().current('analytics.*')">
                        Analitik
                    </ResponsiveNavLink>
                </div>

                <template v-if="adminLinks.length">
                    <div class="px-4 pt-3 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-400">Admin</div>
                    <ResponsiveNavLink v-for="link in adminLinks" :key="link.route" :href="route(link.route)" :active="route().current(link.active)">
                        {{ link.label }}
                    </ResponsiveNavLink>
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

        <!-- While the next page loads, show its skeleton. The current page stays
             mounted underneath (v-show) so its state survives a cancelled visit. -->
        <PageSkeleton v-if="navigationSkeleton.visible" :variant="navigationSkeleton.variant" />

        <div v-show="!navigationSkeleton.visible">
            <header class="bg-white border-b border-gray-100" v-if="$slots.header">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
