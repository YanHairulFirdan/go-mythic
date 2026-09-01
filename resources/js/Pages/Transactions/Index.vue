<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight, ChevronRight, Plus } from '@lucide/vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';

type TransactionType = 'income' | 'expense';

interface Row {
    id: number;
    type: TransactionType;
    amount: number;
    transaction_date: string;
    category: string | null;
    payment_method: string;
    notes: string | null;
}

interface Category {
    id: number;
    name: string;
    type: TransactionType;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    total: number;
}

interface Filters {
    type: TransactionType | null;
    category_id: number | null;
    date_from: string | null;
    date_to: string | null;
}

interface Props {
    transactions: Paginated<Row>;
    categories: Category[];
    filters: Filters;
}

const props = defineProps<Props>();

const typeTabs: Array<{ value: TransactionType | null; label: string }> = [
    { value: null, label: 'Semua' },
    { value: 'income', label: 'Pemasukan' },
    { value: 'expense', label: 'Pengeluaran' },
];

const form = reactive<Filters>({
    type: props.filters.type,
    category_id: props.filters.category_id,
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
});

const createHref = route('transactions.create');
const showHref = (id: number): string => route('transactions.show', id);

const availableCategories = computed((): Category[] =>
    form.type === null ? props.categories : props.categories.filter((category) => category.type === form.type));

const reloading = ref(false);

const reload = (): void => {
    router.get(route('transactions.index'), {
        type: form.type ?? undefined,
        category_id: form.category_id ?? undefined,
        date_from: form.date_from ?? undefined,
        date_to: form.date_to ?? undefined,
    }, {
        only: ['transactions', 'filters'],
        reset: ['transactions'],
        preserveState: true,
        replace: true,
        onStart: () => { reloading.value = true; },
        onFinish: () => { reloading.value = false; },
    });
};

const selectType = (value: TransactionType | null): void => {
    form.type = value;
    // Drop a category filter that no longer belongs to the chosen type.
    if (form.category_id !== null && !availableCategories.value.some((category) => category.id === form.category_id)) {
        form.category_id = null;
    }
    reload();
};

let dateTimer: ReturnType<typeof setTimeout> | undefined;
watch(() => [form.date_from, form.date_to], () => {
    clearTimeout(dateTimer);
    dateTimer = setTimeout(reload, 400);
});
watch(() => form.category_id, reload);

const formatRupiah = (value: number): string => `Rp${Number(value).toLocaleString('id-ID')}`;
const formatDate = (value: string): string =>
    new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });

const isEmpty = computed((): boolean => props.transactions.data.length === 0);
const hasActiveFilter = computed((): boolean =>
    form.type !== null || form.category_id !== null || form.date_from !== null || form.date_to !== null);
</script>

<template>
    <Head title="Transaksi" />

    <PrototypeLayout>
        <section class="flex items-center justify-between pb-3 pt-4">
            <h1 class="text-xl font-bold tracking-tight">Transaksi</h1>
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ props.transactions.total }} entri</span>
        </section>

        <div class="flex gap-1 rounded-xl bg-slate-100 p-1" role="tablist" aria-label="Jenis transaksi">
            <button
                v-for="tab in typeTabs"
                :key="tab.label"
                type="button"
                role="tab"
                :aria-selected="form.type === tab.value"
                :class="[
                    'flex-1 rounded-lg px-3 py-1.5 text-xs font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500',
                    form.type === tab.value ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700',
                ]"
                @click="selectType(tab.value)"
            >
                {{ tab.label }}
            </button>
        </div>

        <div class="mt-3 grid grid-cols-2 gap-2">
            <select
                v-model="form.category_id"
                class="col-span-2 rounded-lg border-slate-300 text-sm focus:border-primary-500 focus:ring-primary-500"
                aria-label="Filter kategori"
            >
                <option :value="null">Semua kategori</option>
                <option v-for="category in availableCategories" :key="category.id" :value="category.id">
                    {{ category.name }}
                </option>
            </select>
            <label class="block">
                <span class="mb-1 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Dari</span>
                <input
                    v-model="form.date_from"
                    type="date"
                    class="block w-full rounded-lg border-slate-300 text-sm focus:border-primary-500 focus:ring-primary-500"
                />
            </label>
            <label class="block">
                <span class="mb-1 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Sampai</span>
                <input
                    v-model="form.date_to"
                    type="date"
                    class="block w-full rounded-lg border-slate-300 text-sm focus:border-primary-500 focus:ring-primary-500"
                />
            </label>
        </div>

        <section class="mt-3 pb-4" aria-label="Daftar transaksi" :aria-busy="reloading">
            <InfiniteScroll
                data="transactions"
                as="div"
                :class="['divide-y divide-slate-100 transition-opacity', reloading ? 'pointer-events-none opacity-50' : '']"
            >
                <Link
                    v-for="transaction in props.transactions.data"
                    :key="transaction.id"
                    :href="showHref(transaction.id)"
                    class="flex items-center gap-3 py-3.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500"
                >
                    <span
                        :class="transaction.type === 'income' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'"
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl"
                    >
                        <ArrowUpRight v-if="transaction.type === 'income'" class="size-[18px]" />
                        <ArrowDownLeft v-else class="size-[18px]" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <strong class="block truncate text-xs font-bold text-slate-800">{{ transaction.category ?? 'Tanpa kategori' }}</strong>
                        <small class="mt-1 block truncate text-[10px] text-slate-400">
                            {{ formatDate(transaction.transaction_date) }}<template v-if="transaction.notes"> · {{ transaction.notes }}</template>
                        </small>
                    </span>
                    <span
                        :class="transaction.type === 'income' ? 'text-emerald-600' : 'text-rose-600'"
                        class="shrink-0 text-xs font-extrabold tabular-nums"
                    >
                        {{ transaction.type === 'income' ? '+' : '-' }}{{ formatRupiah(transaction.amount) }}
                    </span>
                    <ChevronRight class="size-4 shrink-0 text-slate-300" />
                </Link>

                <template #next="{ loading }">
                    <p v-if="loading" class="py-3 text-center text-xs text-slate-400">Memuat…</p>
                </template>
            </InfiniteScroll>

            <p v-if="isEmpty" class="py-10 text-center text-sm text-slate-400">
                {{ hasActiveFilter ? 'Tidak ada transaksi yang cocok dengan filter.' : 'Belum ada transaksi.' }}
            </p>
        </section>

        <Link
            :href="createHref"
            aria-label="Tambah transaksi"
            class="fixed bottom-24 right-5 z-20 flex size-14 items-center justify-center rounded-full bg-primary-600 text-white shadow-lg shadow-primary-200 transition hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 sm:absolute sm:bottom-20 sm:right-5"
        >
            <Plus class="size-6" />
        </Link>
    </PrototypeLayout>
</template>
