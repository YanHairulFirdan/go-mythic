<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight, ChevronRight, CirclePlus, FilePlus2, Landmark, TrendingUp } from '@lucide/vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';

const props = defineProps({
    user: { type: Object, default: () => ({ name: 'Budi Santoso' }) },
    summary: {
        type: Object,
        default: () => ({
            netProfit: 'Rp4.250.000',
            income: 'Rp7,1jt',
            expense: 'Rp2,8jt',
            capital: 'Rp12.900.000',
        }),
    },
    recentTransactions: {
        type: Array,
        default: () => [
            { name: 'Jasa Cleaning Rumah', date: '20 Agu 2026', amount: '+Rp850.000', type: 'income' },
            { name: 'Belanja bahan', date: '19 Agu 2026', amount: '-Rp250.000', type: 'expense' },
        ],
    },
});

const quickActions = [
    { label: 'Catat transaksi', icon: CirclePlus, href: route('transactions.create') },
    { label: 'Buat invoice', icon: FilePlus2, href: '#invoices' },
    { label: 'Lihat laporan', icon: TrendingUp, href: route('reports.profit-loss') },
    { label: 'Atur modal/kas', icon: Landmark, href: '#capital' },
];
</script>

<template>
    <Head title="Beranda" />

    <PrototypeLayout :user="props.user">
        <section class="pb-5 pt-4">
            <p class="text-sm text-slate-500">Selamat datang kembali,</p>
            <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-950">
                {{ props.user.name || 'Budi Santoso' }}
            </h1>
        </section>

        <section class="relative overflow-hidden rounded-3xl bg-indigo-600 p-5 text-white shadow-lg shadow-indigo-200" aria-labelledby="profit-title">
            <div class="absolute -right-14 -top-16 size-44 rounded-full border-[22px] border-indigo-400/20" />
            <div class="absolute -bottom-20 right-8 size-40 rounded-full border-[18px] border-indigo-400/10" />
            <div class="relative">
                <div class="flex items-center justify-between">
                    <p id="profit-title" class="text-xs font-semibold text-indigo-100">Laba bersih · Bulan ini</p>
                    <span class="flex items-center gap-1 rounded-full bg-white/15 px-2 py-1 text-[10px] font-semibold text-indigo-50">
                        <TrendingUp class="size-3" /> +12,8%
                    </span>
                </div>
                <p class="mt-3 text-[2rem] font-extrabold tracking-tight">{{ props.summary.netProfit }}</p>
                <div class="mt-5 h-2 overflow-hidden rounded-full bg-indigo-400/50" aria-label="Perbandingan pemasukan dan pengeluaran">
                    <div class="h-full w-[72%] rounded-full bg-emerald-300" />
                </div>
                <div class="mt-2 flex justify-between text-[10px] text-indigo-100">
                    <span>Pemasukan {{ props.summary.income }}</span>
                    <span>Pengeluaran {{ props.summary.expense }}</span>
                </div>
            </div>
        </section>

        <section class="mt-4 grid grid-cols-2 gap-3">
            <Card label="Modal kas saat ini" :amount="props.summary.capital" />
            <Card label="Transaksi bulan ini" amount="60" note="dari 150/hari">
                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full w-[40%] rounded-full bg-emerald-500" /></div>
            </Card>
        </section>

        <section class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-3.5 text-sm text-amber-800" aria-label="Perhatian">
            <div class="flex gap-3">
                <span class="mt-0.5 text-base" aria-hidden="true">!</span>
                <div>
                    <p class="font-bold">3 invoice belum lunas</p>
                    <p class="mt-0.5 text-xs text-amber-700">1 invoice terpakai sebagian · Periksa sebelum jatuh tempo</p>
                </div>
                <ChevronRight class="ml-auto mt-0.5 size-4 shrink-0" />
            </div>
        </section>

        <section class="mt-6" aria-labelledby="quick-actions-title">
            <div class="mb-3 flex items-center justify-between">
                <h2 id="quick-actions-title" class="text-sm font-bold">Aksi cepat</h2>
                <span class="text-[10px] font-medium uppercase tracking-wider text-slate-400">MVP</span>
            </div>
            <div class="grid grid-cols-2 gap-2.5">
                <Link v-for="action in quickActions" :key="action.label" :href="action.href" class="group flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3 text-left transition hover:border-indigo-200 hover:bg-indigo-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 transition group-hover:bg-indigo-100"><component :is="action.icon" class="size-[18px]" /></span>
                    <span class="text-xs font-semibold leading-tight text-slate-700">{{ action.label }}</span>
                </Link>
            </div>
        </section>

        <section class="mt-7 pb-4" aria-labelledby="recent-title">
            <div class="mb-2 flex items-center justify-between">
                <h2 id="recent-title" class="text-sm font-bold">Transaksi terbaru</h2>
                <Link :href="route('transactions.index')" class="text-xs font-bold text-indigo-600 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">Lihat semua <span aria-hidden="true">→</span></Link>
            </div>
            <div class="divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white px-3">
                <Link v-for="transaction in props.recentTransactions" :key="transaction.name" href="#transaction-detail" class="flex items-center gap-3 py-3.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500">
                    <span :class="transaction.type === 'income' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'" class="flex size-9 shrink-0 items-center justify-center rounded-xl"><ArrowUpRight v-if="transaction.type === 'income'" class="size-[18px]" /><ArrowDownLeft v-else class="size-[18px]" /></span>
                    <span class="min-w-0 flex-1"><strong class="block truncate text-xs font-bold text-slate-800">{{ transaction.name }}</strong><small class="mt-1 block text-[10px] text-slate-400">{{ transaction.date }}</small></span>
                    <span :class="transaction.type === 'income' ? 'text-emerald-600' : 'text-rose-600'" class="text-xs font-extrabold tabular-nums">{{ transaction.amount }}</span>
                    <ChevronRight class="size-4 text-slate-300" />
                </Link>
            </div>
        </section>
    </PrototypeLayout>
</template>
