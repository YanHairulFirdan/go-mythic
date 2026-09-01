<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight, ChevronLeft, Paperclip } from '@lucide/vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';

type TransactionType = 'income' | 'expense';

interface TransactionDetail {
    id: number;
    type: TransactionType;
    amount: number;
    transaction_date: string;
    category: string | null;
    payment_method: string;
    notes: string | null;
    recorded_by: string | null;
    created_at: string | null;
    last_updated_by: string | null;
    last_updated_at: string | null;
    attachment_url: string | null;
}

interface Props {
    transaction: TransactionDetail;
}

const props = defineProps<Props>();

const paymentLabels: Record<string, string> = {
    cash: 'Tunai',
    transfer: 'Transfer bank',
    qris: 'QRIS',
    other: 'Lainnya',
};

const backHref = route('transactions.index');

const isIncome = computed((): boolean => props.transaction.type === 'income');

const formatRupiah = (value: number): string => `Rp${Number(value).toLocaleString('id-ID')}`;
const formatDate = (value: string | null): string => (value
    ? new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
    : '—');
const formatDateTime = (value: string | null): string => (value
    ? new Date(value).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    : '—');

const rows = computed((): Array<[string, string]> => [
    ['Jenis', isIncome.value ? 'Pemasukan' : 'Pengeluaran'],
    ['Kategori', props.transaction.category ?? 'Tanpa kategori'],
    ['Tanggal', formatDate(props.transaction.transaction_date)],
    ['Metode', paymentLabels[props.transaction.payment_method] ?? props.transaction.payment_method],
    ['Dicatat oleh', props.transaction.recorded_by ?? '—'],
]);
</script>

<template>
    <Head title="Detail transaksi" />

    <PrototypeLayout>
        <section class="flex items-center gap-3 pb-5 pt-4">
            <Link
                :href="backHref"
                aria-label="Kembali ke transaksi"
                class="flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <ChevronLeft class="size-5" />
            </Link>
            <h1 class="text-xl font-bold tracking-tight">Detail transaksi</h1>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4" aria-label="Nominal">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Nominal</span>
            <div
                :class="isIncome ? 'text-emerald-600' : 'text-rose-600'"
                class="mt-1 text-2xl font-extrabold tabular-nums tracking-tight"
            >
                {{ isIncome ? '+' : '-' }}{{ formatRupiah(props.transaction.amount) }}
            </div>
            <div :class="isIncome ? 'text-emerald-600' : 'text-rose-600'" class="mt-1 flex items-center gap-1 text-xs font-bold">
                <ArrowUpRight v-if="isIncome" class="size-4" />
                <ArrowDownLeft v-else class="size-4" />
                {{ isIncome ? 'Pemasukan' : 'Pengeluaran' }}
            </div>
        </section>

        <section class="mt-4 rounded-2xl border border-slate-200 bg-white px-4" aria-label="Informasi transaksi">
            <div
                v-for="row in rows"
                :key="row[0]"
                class="flex items-start justify-between gap-4 border-b border-slate-100 py-3.5 text-xs last:border-b-0"
            >
                <span class="text-slate-400">{{ row[0] }}</span>
                <strong class="text-right font-semibold text-slate-700">{{ row[1] }}</strong>
            </div>
        </section>

        <section v-if="props.transaction.notes" class="mt-4 rounded-2xl border border-slate-200 bg-white p-4" aria-label="Catatan">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Catatan</span>
            <p class="mt-1.5 whitespace-pre-line text-sm text-slate-700">{{ props.transaction.notes }}</p>
        </section>

        <a
            v-if="props.transaction.attachment_url"
            :href="props.transaction.attachment_url"
            target="_blank"
            rel="noopener"
            class="mt-4 flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm font-semibold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
        >
            <Paperclip class="size-4 shrink-0" />
            Lihat lampiran
        </a>

        <p
            v-if="props.transaction.last_updated_by"
            class="mt-4 text-[11px] text-slate-400"
        >
            Terakhir diubah oleh {{ props.transaction.last_updated_by }} · {{ formatDateTime(props.transaction.last_updated_at) }}
        </p>
        <p v-else class="mt-4 text-[11px] text-slate-400">
            Dicatat {{ formatDateTime(props.transaction.created_at) }}
        </p>
    </PrototypeLayout>
</template>
