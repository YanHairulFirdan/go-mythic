<script setup>
import { ChevronDown } from '@lucide/vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import BottomNav from '@/Components/app/BottomNav.vue';
import CapitalAlertBanner from '@/Components/app/CapitalAlertBanner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';

const page = usePage();
const logoUrl = computed(() => page.props.branding?.logoUrl ?? null);
const authUser = computed(() => page.props.auth?.user ?? null);
const initial = computed(() => (authUser.value?.name ?? '').charAt(0).toUpperCase());
</script>

<template>
    <div class="flex min-h-dvh justify-center bg-[#f7f8fc] text-slate-900 sm:p-6">
        <div class="relative flex h-dvh w-full max-w-md flex-col overflow-hidden bg-[#f7f8fc] sm:h-[760px] sm:rounded-[2rem] sm:border sm:border-slate-200 sm:shadow-xl">
            <CapitalAlertBanner />
            <header class="flex shrink-0 items-center justify-between px-5 pb-3 pt-6">
                <Link :href="route('dashboard')" class="flex items-center gap-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                    <img v-if="logoUrl" :src="logoUrl" alt="Logo perusahaan" class="h-9 w-auto max-w-[150px] object-contain" />
                    <template v-else>
                        <span class="flex size-9 items-center justify-center rounded-xl bg-primary-600 text-lg font-black text-white shadow-sm shadow-primary-200">✦</span>
                        <span class="text-sm font-extrabold tracking-tight">Sparta Ledger</span>
                    </template>
                </Link>
                <div class="flex items-center gap-2">
                    <Dropdown align="right" width="48" content-classes="py-1 bg-white">
                        <template #trigger>
                            <button
                                type="button"
                                aria-label="Menu akun"
                                class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-2 py-2 text-xs font-semibold text-slate-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            >
                                <span class="flex size-5 items-center justify-center rounded-full bg-primary-100 text-[10px] font-bold text-primary-700">{{ initial }}</span>
                                <ChevronDown class="size-3.5" />
                            </button>
                        </template>

                        <template #content>
                            <div v-if="authUser" class="border-b border-slate-100 px-4 py-3">
                                <p class="truncate text-sm font-semibold text-slate-800">{{ authUser.name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ authUser.email }}</p>
                            </div>
                            <DropdownLink :href="route('profile.edit')">Profil</DropdownLink>
                            <DropdownLink :href="route('logout')" method="post" as="button">Keluar</DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </header>
            <main scroll-region class="scrollbar-hide flex-1 overflow-y-auto px-5 pb-6"><slot /></main>
            <BottomNav />
        </div>
    </div>
</template>
