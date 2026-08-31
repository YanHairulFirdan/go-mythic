<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, ReceiptText } from '@lucide/vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineProps({
    invoices: {
        type: Array,
        default: () => [],
    },
});

const formatRupiah = (value) => `Rp${Number(value || 0).toLocaleString('id-ID')}`;
const formatDate = (value) => (value
    ? new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
    : '');
</script>

<template>
    <Head title="Invoice" />

    <PrototypeLayout>
        <section class="pb-5 pt-4">
            <PageHeader title="Invoice" />
        </section>

        <Link
            :href="route('invoices.create')"
            class="flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
        >
            + Buat invoice
        </Link>

        <section class="mt-4 pb-4" aria-label="Daftar invoice">
            <div class="divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white px-3">
                <Link
                    v-for="invoice in invoices"
                    :key="invoice.id"
                    :href="route('invoices.show', invoice.id)"
                    class="flex items-center gap-3 py-3.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500"
                >
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                        <ReceiptText class="size-[18px]" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <strong class="block truncate text-xs font-bold text-slate-800">{{ invoice.customer || 'Tanpa customer' }}</strong>
                        <small class="mt-1 block text-[10px] text-slate-400">Dibuat {{ formatDate(invoice.created_at) }}</small>
                    </span>
                    <span class="text-xs font-extrabold tabular-nums text-slate-700">{{ formatRupiah(invoice.nominal_total) }}</span>
                    <ChevronRight class="size-4 shrink-0 text-slate-300" />
                </Link>
                <p v-if="invoices.length === 0" class="py-10 text-center text-sm text-slate-400">
                    Belum ada invoice.
                </p>
            </div>
        </section>
    </PrototypeLayout>
</template>
