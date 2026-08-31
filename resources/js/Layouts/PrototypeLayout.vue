<script setup>
import { Bell, ChevronDown } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import BottomNav from '@/Components/app/BottomNav.vue';

defineProps({ user: { type: Object, default: () => ({ name: 'Budi Santoso' }) } });
</script>

<template>
    <div class="min-h-screen bg-[#f7f8fc] text-slate-900 sm:p-6">
        <div class="relative mx-auto min-h-screen max-w-md overflow-hidden bg-[#f7f8fc] pb-24 sm:min-h-[760px] sm:rounded-[2rem] sm:border sm:border-slate-200 sm:shadow-xl">
            <header class="flex items-center justify-between px-5 pb-3 pt-6">
                <Link :href="route('dashboard')" class="flex items-center gap-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-indigo-600 text-lg font-black text-white shadow-sm shadow-indigo-200">✦</span>
                    <span class="text-sm font-extrabold tracking-tight">Sparta Ledger</span>
                </Link>
                <div class="flex items-center gap-2">
                    <button aria-label="Notifikasi" class="relative flex size-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                        <Bell class="size-[17px]" />
                        <span class="absolute right-2 top-2 size-1.5 rounded-full bg-rose-500" />
                    </button>
                    <button class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-2 py-2 text-xs font-semibold text-slate-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                        <span class="flex size-5 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-bold text-indigo-700">{{ user.name?.charAt(0) || 'B' }}</span>
                        <ChevronDown class="size-3.5" />
                    </button>
                </div>
            </header>
            <main class="px-5"><slot /></main>
            <BottomNav />
        </div>
    </div>
</template>
