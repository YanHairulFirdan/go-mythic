<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowUpRight, ChevronLeft, ChevronRight, Pencil, Users } from '@lucide/vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';
import Card from '@/Components/ui/Card.vue';

const props = defineProps({
    customer: {
        type: Object,
        default: () => ({
            id: 1,
            name: 'Toko Bintang',
            contact: '0812-3456-7890',
            address: 'Jl. Diponegoro No.8, Denpasar',
            total: 'Rp2.450.000',
            transactionCount: 9,
            transactions: [
                { id: 1, name: 'Jasa Cleaning — Invoice #INV-0042', date: '14 Agu 2026', amount: '+Rp850.000' },
                { id: 2, name: 'Jasa Cleaning tanpa invoice', date: '2 Agu 2026', amount: '+Rp400.000' },
            ],
        }),
    },
});
</script>

<template>
    <Head title="Detail customer" />

    <PrototypeLayout>
        <section class="flex items-center gap-3 pb-5 pt-4">
            <Link
                :href="route('customers.index')"
                aria-label="Kembali ke customer"
                class="flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:border-indigo-200 hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <ChevronLeft class="size-5" />
            </Link>
            <h1 class="min-w-0 truncate text-xl font-bold tracking-tight">{{ props.customer.name }}</h1>
        </section>

        <Link href="#customer-edit" class="flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
            <Pencil class="size-4" /> Edit customer
        </Link>

        <Card label="Kontak" :amount="props.customer.contact">
            <p class="mt-1 text-xs text-slate-500">{{ props.customer.address }}</p>
        </Card>

        <section class="mt-4 grid grid-cols-2 gap-3" aria-label="Ringkasan customer">
            <Card label="Total nominal" :amount="props.customer.total" />
            <Card label="Transaksi" :amount="String(props.customer.transactionCount)" />
        </section>

        <section class="mt-6 pb-8" aria-labelledby="related-title">
            <div class="mb-2 flex items-center justify-between">
                <h2 id="related-title" class="text-sm font-bold">Transaksi terkait</h2>
                <Link :href="route('transactions.index')" class="text-xs font-bold text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">Lihat semua</Link>
            </div>
            <div class="divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white px-3">
                <Link v-for="transaction in props.customer.transactions" :key="transaction.id" :href="route('transactions.show', transaction.id)" class="flex items-center gap-3 py-3.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><ArrowUpRight class="size-[18px]" /></span>
                    <span class="min-w-0 flex-1"><strong class="block truncate text-xs font-bold text-slate-800">{{ transaction.name }}</strong><small class="mt-1 block text-[10px] text-slate-400">{{ transaction.date }}</small></span>
                    <span class="text-xs font-extrabold tabular-nums text-emerald-600">{{ transaction.amount }}</span>
                    <ChevronRight class="size-4 shrink-0 text-slate-300" />
                </Link>
                <p v-if="props.customer.transactions.length === 0" class="py-10 text-center text-sm text-slate-400">Belum ada transaksi terkait.</p>
            </div>
        </section>
    </PrototypeLayout>
</template>
