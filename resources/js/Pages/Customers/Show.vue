<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, Pencil, Trash2 } from '@lucide/vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';
import Card from '@/Components/ui/Card.vue';

const props = defineProps({
    customer: {
        type: Object,
        required: true,
    },
    transactions: {
        type: Array,
        default: () => [],
    },
    breakdown: {
        type: Object,
        default: () => ({ total: 0, count: 0, last_date: null }),
    },
});

const formatRupiah = (value) => `Rp${Number(value || 0).toLocaleString('id-ID')}`;
const formatDate = (value) => (value
    ? new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
    : '—');

const confirmingDelete = ref(false);
const deleting = ref(false);

const destroy = () => {
    deleting.value = true;
    router.delete(route('customers.destroy', props.customer.id), {
        onFinish: () => {
            deleting.value = false;
            confirmingDelete.value = false;
        },
    });
};
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

        <div class="my-5 flex gap-3">
            <Link :href="route('customers.edit', props.customer.id)" class="flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                <Pencil class="size-4" /> Edit
            </Link>
            <button type="button" @click="confirmingDelete = true" class="flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl border border-rose-200 bg-white px-4 py-3 text-sm font-semibold text-rose-600 transition hover:bg-rose-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500">
                <Trash2 class="size-4" /> Hapus
            </button>
        </div>

        <div
            v-if="confirmingDelete"
            class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center"
            role="presentation"
            @click.self="confirmingDelete = false"
        >
            <section class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="delete-customer-title">
                <h2 id="delete-customer-title" class="text-sm font-bold text-slate-900">Hapus customer?</h2>
                <p class="mt-1.5 text-xs text-slate-500">
                    <strong class="text-slate-700">{{ props.customer.name }}</strong> akan dihapus. Transaksi & invoice yang sudah terkait tetap tersimpan.
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
                        class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-rose-700 disabled:opacity-50"
                        @click="destroy"
                    >
                        {{ deleting ? 'Menghapus…' : 'Hapus' }}
                    </button>
                </div>
            </section>
        </div>

        <Card label="Kontak" :amount="props.customer.contact || '—'">
            <p class="mt-1 text-xs text-slate-500">{{ props.customer.address || 'Alamat belum diisi' }}</p>
        </Card>

        <!-- US-CUST-03 AC2: breakdown transaksi income, on-the-fly -->
        <section class="mt-4 grid grid-cols-3 gap-2" aria-label="Ringkasan transaksi">
            <div class="rounded-xl border border-slate-200 bg-white p-3">
                <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total</div>
                <div class="mt-1 text-sm font-bold tabular-nums text-slate-800">{{ formatRupiah(props.breakdown.total) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-3">
                <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Transaksi</div>
                <div class="mt-1 text-sm font-bold tabular-nums text-slate-800">{{ props.breakdown.count }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-3">
                <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Terakhir</div>
                <div class="mt-1 text-xs font-bold text-slate-800">{{ formatDate(props.breakdown.last_date) }}</div>
            </div>
        </section>

        <!-- US-CUST-03 AC1: daftar transaksi income terkait -->
        <section class="mt-4 pb-4" aria-labelledby="customer-transactions-title">
            <h2 id="customer-transactions-title" class="mb-2 text-sm font-bold">Transaksi pemasukan</h2>
            <div class="divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white px-3">
                <Link
                    v-for="transaction in props.transactions"
                    :key="transaction.id"
                    :href="route('transactions.show', transaction.id)"
                    class="flex items-center gap-3 py-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500"
                >
                    <span class="min-w-0 flex-1">
                        <strong class="block truncate text-xs font-bold text-slate-800">{{ transaction.category ?? 'Tanpa kategori' }}</strong>
                        <small class="mt-1 block text-[10px] text-slate-400">
                            {{ formatDate(transaction.transaction_date) }}<template v-if="transaction.invoice_id"> · Invoice #{{ transaction.invoice_id }}</template>
                        </small>
                    </span>
                    <span class="shrink-0 text-xs font-extrabold tabular-nums text-emerald-600">+{{ formatRupiah(transaction.amount) }}</span>
                </Link>
                <p v-if="props.transactions.length === 0" class="py-8 text-center text-xs text-slate-400">
                    Belum ada transaksi pemasukan dari customer ini.
                </p>
            </div>
        </section>
    </PrototypeLayout>
</template>
