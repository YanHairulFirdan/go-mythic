<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight, ChevronLeft, Download, Pencil, Trash2 } from '@lucide/vue';
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
    attachment_download_url: string | null;
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
const editHref = route('transactions.edit', props.transaction.id);

const isIncome = computed((): boolean => props.transaction.type === 'income');

const confirmingDelete = ref(false);
const deleting = ref(false);

const destroy = (): void => {
    deleting.value = true;
    router.delete(route('transactions.destroy', props.transaction.id), {
        onFinish: () => { deleting.value = false; },
    });
};

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

        <section
            v-if="props.transaction.attachment_url"
            class="mt-4 rounded-2xl border border-slate-200 bg-white p-4"
            aria-label="Lampiran"
        >
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Lampiran</span>
            <img
                :src="props.transaction.attachment_url"
                alt="Lampiran transaksi"
                class="mt-2 max-h-72 w-full rounded-xl border border-slate-200 bg-slate-50 object-contain"
            />
            <a
                :href="props.transaction.attachment_download_url ?? props.transaction.attachment_url"
                download
                class="mt-3 flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <Download class="size-4 shrink-0" />
                Unduh lampiran
            </a>
        </section>

        <p
            v-if="props.transaction.last_updated_by"
            class="mt-4 text-[11px] text-slate-400"
        >
            Terakhir diubah oleh {{ props.transaction.last_updated_by }} · {{ formatDateTime(props.transaction.last_updated_at) }}
        </p>
        <p v-else class="mt-4 text-[11px] text-slate-400">
            Dicatat {{ formatDateTime(props.transaction.created_at) }}
        </p>

        <div class="grid grid-cols-2 gap-3 pb-8 pt-5">
            <Link
                :href="editHref"
                class="flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <Pencil class="size-4" /> Edit
            </Link>
            <button
                type="button"
                class="flex min-h-11 items-center justify-center gap-2 rounded-xl border border-rose-200 bg-white px-4 py-3 text-sm font-semibold text-rose-600 transition hover:bg-rose-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500"
                @click="confirmingDelete = true"
            >
                <Trash2 class="size-4" /> Hapus
            </button>
        </div>

        <div
            v-if="confirmingDelete"
            class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center"
            role="presentation"
            @click.self="confirmingDelete = false"
        >
            <section
                class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="delete-transaction-title"
            >
                <h2 id="delete-transaction-title" class="text-sm font-bold text-slate-900">Hapus transaksi?</h2>
                <p class="mt-1.5 text-xs text-slate-500">
                    Transaksi ini akan dihapus (soft delete) dan tidak lagi muncul di daftar maupun laporan.
                </p>
                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                        @click="confirmingDelete = false"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        :disabled="deleting"
                        class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-rose-700 disabled:opacity-40"
                        @click="destroy"
                    >
                        Hapus
                    </button>
                </div>
            </section>
        </div>
    </PrototypeLayout>
</template>
