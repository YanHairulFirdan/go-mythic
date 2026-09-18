<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, Plus, ReceiptText, Search } from '@lucide/vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { formatRupiah } from '@/utils/currency';

const props = defineProps({
    invoices: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ search: '', status: 'all' }),
    },
});

const search = ref(props.filters.search ?? '');
// US-INV-07 AC6: status filter — semua / belum dibayar / dp / lunas / jatuh tempo.
const status = ref(props.filters.status ?? 'all');
const reloading = ref(false);

let timer;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('invoices.index'), { search: search.value.trim() || undefined, status: status.value === 'all' ? undefined : status.value }, {
            only: ['invoices', 'filters'],
            preserveState: true,
            replace: true,
            onStart: () => { reloading.value = true; },
            onFinish: () => { reloading.value = false; },
        });
    }, 300);
});

const reloadWithStatus = (value) => {
    status.value = value;
    router.get(route('invoices.index'), { search: search.value.trim() || undefined, status: value === 'all' ? undefined : value }, {
        only: ['invoices', 'filters'],
        preserveState: true,
        replace: true,
        onStart: () => { reloading.value = true; },
        onFinish: () => { reloading.value = false; },
    });
};

const statusOptions = [
    { key: 'all', label: 'Semua' },
    { key: 'belum_dibayar', label: 'Belum dibayar' },
    { key: 'dp', label: 'DP' },
    { key: 'lunas', label: 'Lunas' },
    { key: 'jatuh_tempo', label: 'Jatuh tempo' },
];

// US-INV-07 AC2/AC3: pill colours per derived status; overdue wins visually.
const statusPill = (invoice) => {
    if (invoice.is_overdue) {
        return 'bg-rose-50 text-rose-700';
    }
    return { lunas: 'bg-emerald-50 text-emerald-700', dp: 'bg-amber-50 text-amber-700', belum_dibayar: 'bg-slate-100 text-slate-600' }[invoice.status_key] ?? 'bg-slate-100 text-slate-600';
};

const formatDate = (value) => (value
    ? new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
    : '');

// US-INV-04: progress is SUM(linked transactions) vs nominal_total, on-the-fly.
const progressPct = (invoice) => {
    const total = Number(invoice.nominal_total || 0);
    if (total <= 0) {
        return 0;
    }
    return Math.min(100, Math.round((Number(invoice.linked_total || 0) / total) * 100));
};
</script>

<template>
    <Head title="Invoice" />

    <PrototypeLayout>
        <section class="pb-4 pt-4">
            <PageHeader title="Invoice" />
        </section>

        <div class="relative">
            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
            <input
                v-model="search"
                type="search"
                placeholder="Cari nama customer"
                aria-label="Cari invoice berdasarkan customer"
                class="block w-full rounded-lg border-slate-300 pl-9 text-sm focus:border-primary-500 focus:ring-primary-500"
            />
        </div>

        <div class="mt-2.5 -mx-4 overflow-x-auto px-4 pb-0.5" role="group" aria-label="Filter status invoice">
            <div class="flex w-max gap-2">
                <button
                    v-for="option in statusOptions"
                    :key="option.key"
                    type="button"
                    :aria-pressed="status === option.key"
                    class="min-h-8 whitespace-nowrap rounded-full border px-3 text-xs font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                    :class="status === option.key ? 'border-primary-600 bg-primary-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-primary-200'"
                    @click="reloadWithStatus(option.key)"
                >
                    {{ option.label }}
                </button>
            </div>
        </div>

        <section class="mt-3 pb-4" aria-label="Daftar invoice" :aria-busy="reloading">
            <div :class="['divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white px-3 transition-opacity', reloading ? 'pointer-events-none opacity-50' : '']">
                <Link
                    v-for="invoice in props.invoices"
                    :key="invoice.id"
                    :href="route('invoices.show', invoice.id)"
                    class="block py-3.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500"
                >
                    <div class="flex items-center gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600">
                            <ReceiptText class="size-[18px]" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <strong class="block truncate text-xs font-bold text-slate-800">{{ invoice.customer || 'Tanpa customer' }}</strong>
                            <small class="mt-1 block text-[10px] text-slate-400">Dibuat {{ formatDate(invoice.created_at) }}</small>
                        </span>
                        <span class="text-xs font-extrabold tabular-nums text-slate-700">{{ formatRupiah(invoice.nominal_total) }}</span>
                        <ChevronRight class="size-4 shrink-0 text-slate-300" />
                    </div>
                    <div class="mt-2 flex items-center gap-2 pl-[52px]">
                        <span :class="['rounded-full px-2 py-0.5 text-[9px] font-extrabold', statusPill(invoice)]">
                            {{ invoice.is_overdue ? 'JATUH TEMPO' : invoice.status }}
                        </span>
                        <span v-if="invoice.due_date" class="text-[10px] text-slate-400">Tempo {{ formatDate(invoice.due_date) }}</span>
                    </div>
                    <div class="mt-2 flex items-center gap-2 pl-[52px]">
                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-primary-500" :style="{ width: `${progressPct(invoice)}%` }" />
                        </div>
                        <span class="shrink-0 text-[10px] font-bold tabular-nums text-slate-500">
                            {{ formatRupiah(invoice.linked_total) }} / {{ formatRupiah(invoice.nominal_total) }}
                        </span>
                    </div>
                </Link>
                <p v-if="props.invoices.length === 0" class="py-10 text-center text-sm text-slate-400">
                    {{ search.trim() ? 'Tidak ada invoice untuk customer itu.' : 'Belum ada invoice.' }}
                </p>
            </div>
        </section>

        <Link
            :href="route('invoices.create')"
            aria-label="Buat invoice"
            class="fixed bottom-24 right-5 z-20 flex size-14 items-center justify-center rounded-full bg-primary-600 text-white shadow-lg shadow-primary-200 transition hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 sm:absolute sm:bottom-20 sm:right-5"
        >
            <Plus class="size-6" />
        </Link>
    </PrototypeLayout>
</template>
