<script setup>
import { Link } from '@inertiajs/vue3';
import {
    Building2,
    CreditCard,
    LayoutDashboard,
    LogOut,
    PanelLeftClose,
    PanelLeftOpen,
} from '@lucide/vue';
import { onMounted, ref } from 'vue';

const STORAGE_KEY = 'admin.sidebar.collapsed';

const collapsed = ref(false);

onMounted(() => {
    try {
        collapsed.value = localStorage.getItem(STORAGE_KEY) === '1';
    } catch (e) {
        // localStorage unavailable — keep the default expanded state.
    }
});

const toggle = () => {
    collapsed.value = !collapsed.value;

    try {
        localStorage.setItem(STORAGE_KEY, collapsed.value ? '1' : '0');
    } catch (e) {
        // Ignore persistence failures (private mode, blocked storage).
    }
};

const navLinks = [
    { label: 'Dashboard', route: 'admin.dashboard', match: 'admin.dashboard', icon: LayoutDashboard },
    { label: 'Company', route: 'admin.companies.index', match: 'admin.companies.*', icon: Building2 },
    { label: 'Pembayaran', route: 'admin.payments.index', match: 'admin.payments.*', icon: CreditCard },
];
</script>

<template>
    <div class="flex min-h-screen bg-gray-100">
        <aside
            class="flex flex-col border-r border-gray-200 bg-white transition-[width] duration-200 ease-in-out"
            :class="collapsed ? 'w-16' : 'w-64'"
        >
            <div
                class="flex h-16 items-center border-b border-gray-100 px-3"
                :class="collapsed ? 'justify-center' : 'gap-2'"
            >
                <Link
                    v-if="!collapsed"
                    :href="route('admin.dashboard')"
                    class="flex min-w-0 flex-1 items-center gap-2 font-semibold text-gray-800"
                >
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-md bg-gray-800 text-sm font-bold text-white">SL</span>
                    <span class="truncate text-sm">Super Admin</span>
                </Link>
                <button
                    type="button"
                    class="shrink-0 rounded-md p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                    :aria-label="collapsed ? 'Lebarkan menu' : 'Ciutkan menu'"
                    :aria-expanded="!collapsed"
                    @click="toggle"
                >
                    <component :is="collapsed ? PanelLeftOpen : PanelLeftClose" class="size-5" />
                </button>
            </div>

            <nav aria-label="Navigasi admin" class="flex-1 space-y-1 p-2">
                <Link
                    v-for="link in navLinks"
                    :key="link.route"
                    :href="route(link.route)"
                    class="flex items-center gap-3 rounded-md px-3 py-2 text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                    :class="[
                        route().current(link.match)
                            ? 'bg-gray-100 font-medium text-gray-900'
                            : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800',
                        collapsed && 'justify-center px-0',
                    ]"
                    :title="collapsed ? link.label : undefined"
                    :aria-current="route().current(link.match) ? 'page' : undefined"
                >
                    <component :is="link.icon" class="size-5 shrink-0" />
                    <span v-if="!collapsed" class="truncate">{{ link.label }}</span>
                </Link>
            </nav>

            <div class="border-t border-gray-100 p-2">
                <Link
                    :href="route('admin.logout')"
                    method="post"
                    as="button"
                    class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm text-gray-500 hover:bg-gray-50 hover:text-gray-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                    :class="collapsed && 'justify-center px-0'"
                    :title="collapsed ? 'Keluar' : undefined"
                >
                    <LogOut class="size-5 shrink-0" />
                    <span v-if="!collapsed" class="truncate">Keluar</span>
                </Link>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header v-if="$slots.header" class="border-b border-gray-200 bg-white">
                <div class="px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main class="flex-1">
                <slot />
            </main>
        </div>
    </div>
</template>
