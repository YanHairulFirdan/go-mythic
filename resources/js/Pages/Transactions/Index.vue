<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight, ChevronRight, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';
import Card from '@/Components/ui/Card.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({
            income: '42/150',
            expense: '18/150',
        }),
    },
    transactions: {
        type: Array,
        default: () => [
            {
                id: 1,
                name: 'Jasa Cleaning — Toko Bintang',
                detail: 'Customer · INV-0042',
                date: '14 Agu 2026',
                amount: '+Rp850.000',
                type: 'income',
                today: true,
            },
            {
                id: 2,
                name: 'Beli perlengkapan',
                detail: 'Detergen & lap microfiber',
                date: '14 Agu 2026',
                amount: '-Rp320.000',
                type: 'expense',
                today: true,
            },
        ],
    },
});

const selectedFilter = ref('Semua');
const filters = ['Semua', 'Hari ini'];
const filteredTransactions = computed(() => selectedFilter.value === 'Hari ini'
    ? props.transactions.filter((transaction) => transaction.today)
    : props.transactions);
</script>

<template>
    <Head title="Transaksi" />

    <PrototypeLayout>
        <section class="pb-5 pt-4">
            <PageHeader title="Transaksi" />
        </section>

        <section class="grid grid-cols-2 gap-3" aria-label="Kuota transaksi hari ini">
            <Card label="Pemasukan" :amount="props.summary.income" />
            <Card label="Pengeluaran" :amount="props.summary.expense" />
        </section>

        <div class="my-4 flex rounded-xl bg-slate-100 p-1" role="tablist" aria-label="Filter transaksi">
            <button
                v-for="filter in filters"
                :key="filter"
                type="button"
                role="tab"
                :aria-selected="selectedFilter === filter"
                :class="selectedFilter === filter ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                class="flex-1 rounded-lg px-3 py-2 text-xs font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                @click="selectedFilter = filter"
            >
                {{ filter }}
            </button>
        </div>

        <section class="pb-24" aria-label="Daftar transaksi">
            <Link
                v-for="transaction in filteredTransactions"
                :key="transaction.id"
                href="#transaction-detail"
                class="flex items-center gap-3 border-b border-slate-100 py-3.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500"
            >
                <span
                    :class="transaction.type === 'income' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'"
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl"
                >
                    <ArrowUpRight v-if="transaction.type === 'income'" class="size-[18px]" />
                    <ArrowDownLeft v-else class="size-[18px]" />
                </span>
                <span class="min-w-0 flex-1">
                    <strong class="block truncate text-xs font-bold text-slate-800">{{ transaction.name }}</strong>
                    <small class="mt-1 block truncate text-[10px] text-slate-400">{{ transaction.detail }} · {{ transaction.date }}</small>
                </span>
                <span
                    :class="transaction.type === 'income' ? 'text-emerald-600' : 'text-rose-600'"
                    class="text-xs font-extrabold tabular-nums"
                >
                    {{ transaction.amount }}
                </span>
                <ChevronRight class="size-4 shrink-0 text-slate-300" />
            </Link>
            <p v-if="filteredTransactions.length === 0" class="py-10 text-center text-sm text-slate-400">
                Belum ada transaksi untuk filter ini.
            </p>
        </section>

        <Link
            :href="route('transactions.create')"
            aria-label="Tambah transaksi"
            class="fixed bottom-24 right-5 z-20 flex size-14 items-center justify-center rounded-full bg-indigo-600 text-white shadow-lg shadow-indigo-200 transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 sm:absolute sm:bottom-20 sm:right-5"
        >
            <Plus class="size-6" />
        </Link>
    </PrototypeLayout>
</template>
