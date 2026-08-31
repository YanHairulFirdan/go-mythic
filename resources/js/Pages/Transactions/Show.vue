<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight, ChevronLeft } from '@lucide/vue';
import { computed } from 'vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';
import Card from '@/Components/ui/Card.vue';

const props = defineProps({
    transaction: {
        type: Object,
        default: () => ({
            amount: '+Rp850.000',
            type: 'income',
            category: 'Jasa Cleaning Rumah',
            date: '14 Agu 2026',
            method: 'Transfer Bank',
            customer: 'Toko Bintang',
            invoice: 'INV-0042',
            performedBy: 'Made Wirawan',
            recordedBy: 'Budi Santoso',
        }),
    },
});

const isIncome = computed(() => props.transaction.type === 'income');
const details = computed(() => [
    ['Jenis', isIncome.value ? 'Pemasukan' : 'Pengeluaran'],
    ['Kategori', props.transaction.category],
    ['Tanggal', props.transaction.date],
    ['Metode', props.transaction.method],
    ['Customer', isIncome.value ? props.transaction.customer : '—'],
    ['Invoice', isIncome.value ? props.transaction.invoice : '—'],
    ['Dikerjakan oleh', props.transaction.performedBy],
    ['Dicatat oleh', props.transaction.recordedBy],
]);
</script>

<template>
    <Head title="Detail transaksi" />

    <PrototypeLayout>
        <section class="flex items-center gap-3 pb-5 pt-4">
            <Link
                :href="route('transactions.index')"
                aria-label="Kembali ke transaksi"
                class="flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:border-indigo-200 hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <ChevronLeft class="size-5" />
            </Link>
            <h1 class="text-xl font-bold tracking-tight">Detail transaksi</h1>
        </section>

        <Card label="Nominal" :amount="props.transaction.amount">
            <div :class="isIncome ? 'text-emerald-600' : 'text-rose-600'" class="mt-2 flex items-center gap-1 text-xs font-bold">
                <ArrowUpRight v-if="isIncome" class="size-4" />
                <ArrowDownLeft v-else class="size-4" />
                {{ isIncome ? 'Pemasukan' : 'Pengeluaran' }}
            </div>
        </Card>

        <section class="mt-4 rounded-2xl border border-slate-200 bg-white px-4" aria-label="Informasi transaksi">
            <div v-for="detail in details" :key="detail[0]" class="flex items-start justify-between gap-4 border-b border-slate-100 py-3.5 text-xs last:border-b-0">
                <span class="text-slate-400">{{ detail[0] }}</span>
                <strong class="text-right font-semibold text-slate-700">{{ detail[1] }}</strong>
            </div>
        </section>

        <div class="grid grid-cols-2 gap-3 pb-8 pt-5">
            <Link href="#transaction-edit" class="flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                Edit
            </Link>
            <Link href="#transaction-delete" class="flex min-h-11 items-center justify-center rounded-xl border border-rose-200 bg-white px-4 py-3 text-sm font-semibold text-rose-600 transition hover:bg-rose-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500">
                Hapus transaksi
            </Link>
        </div>
    </PrototypeLayout>
</template>
