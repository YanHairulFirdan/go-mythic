<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ChevronRight, CreditCard, Landmark, LogOut, Tags, TrendingUp, UserRound, Users } from '@lucide/vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';

const page = usePage();
const isOwner = computed(() => page.props.auth?.user?.role === 'owner');

const modules = computed(() => [
    { label: 'Laporan P&L', desc: 'Pemasukan, pengeluaran, saldo bersih', icon: TrendingUp, href: route('reports.profit-loss') },
    { label: 'Modal / Kas Usaha', desc: 'Baseline modal dan top-up', icon: Landmark, href: route('capital.index') },
    ...(isOwner.value
        ? [{ label: 'Kelola Kategori', desc: 'Kategori transaksi bawaan dan custom', icon: Tags, href: route('transaction-categories.index') }]
        : []),
    { label: 'Kelola Karyawan', desc: 'Roster worker dan employee ber-akun', icon: Users, href: route('employees.index') },
    { label: 'Langganan', desc: 'Paket dan pembayaran', icon: CreditCard, href: route('subscription.index') },
]);

const account = [
    { label: 'Profil', desc: 'Ubah data akun dan kata sandi', icon: UserRound, href: route('profile.edit') },
];
</script>

<template>
    <Head title="Lainnya" />

    <PrototypeLayout>
        <section class="pb-5 pt-4">
            <h1 class="text-xl font-bold tracking-tight">Lainnya</h1>
        </section>

        <section aria-label="Modul" class="rounded-2xl border border-slate-200 bg-white px-4">
            <Link
                v-for="item in modules"
                :key="item.label"
                :href="item.href"
                class="flex items-center gap-3 border-b border-slate-100 py-3.5 last:border-b-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500"
            >
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600">
                    <component :is="item.icon" class="size-[18px]" />
                </span>
                <span class="min-w-0 flex-1">
                    <strong class="block truncate text-xs font-bold text-slate-800">{{ item.label }}</strong>
                    <small class="mt-1 block truncate text-[10px] text-slate-400">{{ item.desc }}</small>
                </span>
                <ChevronRight class="size-4 shrink-0 text-slate-300" />
            </Link>
        </section>

        <section aria-label="Akun" class="mt-4 rounded-2xl border border-slate-200 bg-white px-4">
            <Link
                v-for="item in account"
                :key="item.label"
                :href="item.href"
                class="flex items-center gap-3 border-b border-slate-100 py-3.5 last:border-b-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500"
            >
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600">
                    <component :is="item.icon" class="size-[18px]" />
                </span>
                <span class="min-w-0 flex-1">
                    <strong class="block truncate text-xs font-bold text-slate-800">{{ item.label }}</strong>
                    <small class="mt-1 block truncate text-[10px] text-slate-400">{{ item.desc }}</small>
                </span>
                <ChevronRight class="size-4 shrink-0 text-slate-300" />
            </Link>
        </section>

        <div class="pb-8 pt-5">
            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl border border-rose-200 bg-white px-4 py-3 text-sm font-semibold text-rose-600 transition hover:bg-rose-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500"
            >
                <LogOut class="size-4" /> Keluar
            </Link>
        </div>
    </PrototypeLayout>
</template>
