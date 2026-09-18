<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, CirclePlus, Lock, Pencil, Trash2 } from '@lucide/vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';
import Card from '@/Components/ui/Card.vue';
import { formatRupiah } from '@/utils/currency';

const props = defineProps({
    invoice: {
        type: Object,
        required: true,
    },
    transactions: {
        type: Array,
        default: () => [],
    },
});

// US-INV-04: on-the-fly progress (SUM transaksi terkait vs nominal_total).
const progressPct = computed(() => {
    const total = Number(props.invoice.nominal_total || 0);
    if (total <= 0) {
        return 0;
    }
    return Math.min(100, Math.round((Number(props.invoice.linked_total || 0) / total) * 100));
});
const hasRemaining = computed(() => Number(props.invoice.remaining || 0) > 0);
const statusClass = computed(() => props.invoice.is_overdue
    ? 'bg-rose-50 text-rose-700'
    : { lunas: 'bg-emerald-50 text-emerald-700', dp: 'bg-amber-50 text-amber-700', belum_dibayar: 'bg-slate-100 text-slate-600' }[props.invoice.status_key] ?? 'bg-slate-100 text-slate-600');
const formatDate = (value) => (value
    ? new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
    : '—');
// US-INV-03: open the transaction form with this invoice pre-selected.
const recordTransactionHref = computed(() => route('transactions.create', { invoice_id: props.invoice.id }));

const confirmingDelete = ref(false);
const deleting = ref(false);

const destroy = () => {
    deleting.value = true;
    router.delete(route('invoices.destroy', props.invoice.id), {
        onFinish: () => {
            deleting.value = false;
            confirmingDelete.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail invoice" />

    <PrototypeLayout>
        <section class="flex items-center gap-3 pb-5 pt-4">
            <Link
                :href="route('invoices.index')"
                aria-label="Kembali ke invoice"
                class="flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:border-primary-200 hover:text-primary-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
                <ChevronLeft class="size-5" />
            </Link>
            <h1 class="min-w-0 truncate text-xl font-bold tracking-tight">Invoice #{{ props.invoice.id }}</h1>
        </section>

        <div v-if="!props.invoice.is_frozen" class="my-5 flex gap-3">
            <Link :href="route('invoices.edit', props.invoice.id)" class="flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                <Pencil class="size-4" /> Edit
            </Link>
            <button type="button" @click="confirmingDelete = true" class="flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl border border-rose-200 bg-white px-4 py-3 text-sm font-semibold text-rose-600 transition hover:bg-rose-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500">
                <Trash2 class="size-4" /> Hapus
            </button>
        </div>
        <div v-else class="my-5 flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-amber-800" role="status">
            <Lock class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <p class="text-xs leading-snug">
                <strong class="font-bold">Invoice terkunci.</strong>
                Sudah punya transaksi terkait — customer, pelaksana, dan rincian item tidak dapat diubah lagi.
            </p>
        </div>

        <Card label="Customer" :amount="props.invoice.customer?.name || '—'">
            <p class="mt-1 text-xs text-slate-500">
                Penanggung jawab: {{ props.invoice.employee?.name || 'Tidak ada' }}
            </p>
        </Card>

        <section class="mt-4" aria-labelledby="items-title">
            <h2 id="items-title" class="mb-2 text-sm font-bold">Rincian item</h2>
            <div class="rounded-2xl border border-slate-200 bg-white px-4">
                <div v-for="item in props.invoice.items" :key="item.id" class="flex items-center justify-between gap-4 border-b border-slate-100 py-3.5 text-xs last:border-b-0">
                    <span class="text-slate-600">{{ item.description }}</span>
                    <strong class="font-semibold tabular-nums text-slate-800">{{ formatRupiah(item.amount) }}</strong>
                </div>
            </div>
        </section>

        <Card label="Total invoice" :amount="formatRupiah(props.invoice.nominal_total)" class="mt-4" />

        <section class="mt-4 rounded-2xl border border-slate-200 bg-white p-4" aria-label="Status pembayaran">
            <div class="flex items-center justify-between gap-3">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Status pembayaran</span>
                <span :class="['rounded-full px-2.5 py-1 text-[10px] font-extrabold', statusClass]">
                    {{ props.invoice.is_overdue ? 'JATUH TEMPO' : props.invoice.status }}
                </span>
            </div>
            <p class="mt-2 text-xs text-slate-500">Jatuh tempo: <strong class="text-slate-700">{{ formatDate(props.invoice.due_date) }}</strong></p>
            <p v-if="props.invoice.is_overdue" class="mt-1 text-xs font-semibold text-rose-600">Sisa saldo belum tertagih.</p>
        </section>

        <section class="mt-4 rounded-2xl border border-slate-200 bg-white p-4" aria-label="Progress invoice">
            <div class="flex items-center justify-between gap-2">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Tercatat lewat transaksi</span>
                <span class="text-xs font-bold tabular-nums text-slate-700">{{ progressPct }}%</span>
            </div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-primary-500" :style="{ width: `${progressPct}%` }" />
            </div>
            <p class="mt-2 text-xs text-slate-500">
                {{ formatRupiah(props.invoice.linked_total) }} dari {{ formatRupiah(props.invoice.nominal_total) }}
                · sisa {{ formatRupiah(props.invoice.remaining) }}
            </p>
            <Link
                v-if="hasRemaining"
                :href="recordTransactionHref"
                class="mt-3 flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2"
            >
                <CirclePlus class="size-4" /> Catat transaksi
            </Link>
        </section>

        <section class="mt-4 pb-4" aria-labelledby="invoice-transactions-title">
            <h2 id="invoice-transactions-title" class="mb-2 text-sm font-bold">Transaksi terkait</h2>
            <div class="divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white px-3">
                <Link
                    v-for="transaction in props.transactions"
                    :key="transaction.id"
                    :href="route('transactions.show', transaction.id)"
                    class="flex items-center gap-3 py-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500"
                >
                    <span class="min-w-0 flex-1">
                        <strong class="block truncate text-xs font-bold text-slate-800">{{ transaction.category ?? 'Tanpa kategori' }}</strong>
                        <small class="mt-1 block text-[10px] text-slate-400">{{ formatDate(transaction.transaction_date) }}</small>
                    </span>
                    <span :class="transaction.type === 'income' ? 'text-emerald-600' : 'text-rose-600'" class="shrink-0 text-xs font-extrabold tabular-nums">
                        {{ transaction.type === 'income' ? '+' : '-' }}{{ formatRupiah(transaction.amount) }}
                    </span>
                </Link>
                <p v-if="props.transactions.length === 0" class="py-8 text-center text-xs text-slate-400">
                    Belum ada transaksi tercatat untuk invoice ini.
                </p>
            </div>
        </section>

        <div
            v-if="confirmingDelete"
            class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center"
            role="presentation"
            @click.self="confirmingDelete = false"
        >
            <section class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="delete-invoice-title">
                <h2 id="delete-invoice-title" class="text-lg font-bold text-slate-800">Hapus invoice?</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Invoice #{{ props.invoice.id }} beserta seluruh rincian itemnya akan dihapus. Tindakan ini tidak bisa dibatalkan.
                </p>
                <div class="mt-4 flex gap-3">
                    <button
                        type="button"
                        class="flex min-h-11 flex-1 items-center justify-center rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                        @click="confirmingDelete = false"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        :disabled="deleting"
                        class="flex min-h-11 flex-1 items-center justify-center rounded-xl bg-rose-600 px-4 py-3 text-sm font-bold text-white hover:bg-rose-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 disabled:opacity-50"
                        @click="destroy"
                    >
                        {{ deleting ? 'Menghapus…' : 'Hapus' }}
                    </button>
                </div>
            </section>
        </div>
    </PrototypeLayout>
</template>
