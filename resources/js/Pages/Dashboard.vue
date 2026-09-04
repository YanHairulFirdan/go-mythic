<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight, ChevronRight, CirclePlus, FilePlus2, Landmark, TrendingDown, TrendingUp } from '@lucide/vue';
import { computed } from 'vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';
import Button from '@/Components/ui/Button.vue';

const page = usePage();

const props = defineProps({
    user: { type: Object, default: null },
    capitalWidget: { type: Object, default: null },
    quotaWidget: { type: Object, default: null },
    // US-INV-06: { outstanding, partial } or null when every invoice is covered.
    invoiceReminderWidget: { type: Object, default: null },
    // Performance card. `basis` is 'capital' (badge = laba ÷ modal over the
    // active capital period) or 'month' (badge = month-over-month net change).
    // `change_percent` / `baseline_amount` are null when there is no baseline.
    summary: {
        type: Object,
        default: () => ({
            basis: 'month',
            income: 0,
            expense: 0,
            net_profit: 0,
            income_ratio_percent: 0,
            change_percent: null,
            baseline_amount: null,
            period_start: null,
            period_end: null,
        }),
    },
    // Latest rows the viewer may see (Employee: only their own), newest first.
    // Each: { id, type, amount, transaction_date, category }.
    recentTransactions: {
        type: Array,
        default: () => [],
    },
});

const isOwner = computed(() => page.props.auth?.user?.role === 'owner');

const currentUser = computed(() => page.props.auth?.user ?? props.user);
const displayName = computed(() => currentUser.value?.name ?? '');

const formatRupiah = (value) => `Rp${Number(value || 0).toLocaleString('id-ID')}`;
const formatDate = (value) => (value
    ? new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
    : '');
const formatDayMonth = (value) => (value
    ? new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })
    : '');

const summaryTitle = computed(() => (props.summary.basis === 'capital'
    ? 'Laba bersih · Periode modal'
    : 'Laba bersih · Bulan ini'));

const netChange = computed(() => props.summary.change_percent);
const netChangeUp = computed(() => (netChange.value ?? 0) >= 0);

// Return-on-capital can legitimately run into the hundreds of %; clamp the
// printed figure so the badge never overflows its pill.
const PERCENT_CAP = 999.9;
const netChangeLabel = computed(() => {
    const value = netChange.value;
    if (value === null || value === undefined) {
        return null;
    }

    const clamped = Math.min(PERCENT_CAP, Math.max(-PERCENT_CAP, value));
    const prefix = Math.abs(value) > PERCENT_CAP ? '>' : '';
    const sign = clamped > 0 ? '+' : clamped < 0 ? '−' : '';
    const magnitude = Math.abs(clamped).toLocaleString('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 1,
    });

    return `${prefix}${sign}${magnitude}%`;
});

const summaryCaption = computed(() => {
    const s = props.summary;

    if (s.basis === 'capital') {
        return `dari modal ${formatRupiah(s.baseline_amount)} · ${formatDayMonth(s.period_start)}–${formatDayMonth(s.period_end)}`;
    }

    if (s.baseline_amount === null || s.baseline_amount === undefined) {
        return null;
    }

    const delta = s.net_profit - s.baseline_amount;
    const direction = delta >= 0 ? 'naik' : 'turun';

    return `${direction} ${formatRupiah(Math.abs(delta))} dari ${formatRupiah(s.baseline_amount)} bulan lalu`;
});

const recentItems = computed(() => props.recentTransactions.map((transaction) => ({
    id: transaction.id,
    type: transaction.type,
    label: transaction.category ?? (transaction.type === 'income' ? 'Pemasukan' : 'Pengeluaran'),
    date: formatDate(transaction.transaction_date),
    amount: `${transaction.type === 'income' ? '+' : '−'}${formatRupiah(transaction.amount)}`,
})));

const quickActions = computed(() => [
    { label: 'Catat transaksi', icon: CirclePlus, href: route('transactions.create') },
    { label: 'Buat invoice', icon: FilePlus2, href: '#invoices' },
    ...(isOwner.value ? [
        { label: 'Lihat laporan', icon: TrendingUp, href: route('reports.profit-loss') },
        { label: 'Atur modal/kas', icon: Landmark, href: route('capital.index') },
    ] : []),
]);

// US-SUB-01: per-type daily quota indicator. Empty (section hidden) for Paid.
const quotaItems = computed(() => {
    if (!props.quotaWidget) {
        return [];
    }

    const build = (type, label) => {
        const data = props.quotaWidget[type];

        return {
            type,
            label,
            used: data.used,
            remaining: data.remaining,
            reached: data.reached,
            nearLimit: data.near_limit,
            percent: Math.min(100, Math.round((data.used / props.quotaWidget.limit) * 100)),
        };
    };

    return [build('income', 'Pemasukan'), build('expense', 'Pengeluaran')];
});
</script>

<template>
    <Head title="Beranda" />

    <PrototypeLayout :user="currentUser">
        <section class="pb-5 pt-4">
            <p class="text-sm text-slate-500">Selamat datang kembali,</p>
            <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-950">
                {{ displayName }}
            </h1>
        </section>

        <section class="relative overflow-hidden rounded-3xl bg-primary-600 p-5 text-white shadow-lg shadow-primary-200" aria-labelledby="profit-title">
            <div class="absolute -right-14 -top-16 size-44 rounded-full border-[22px] border-primary-400/20" />
            <div class="absolute -bottom-20 right-8 size-40 rounded-full border-[18px] border-primary-400/10" />
            <div class="relative">
                <div class="flex items-center justify-between">
                    <p id="profit-title" class="text-xs font-semibold text-primary-100">{{ summaryTitle }}</p>
                    <span v-if="netChangeLabel" class="flex items-center gap-1 rounded-full bg-white/15 px-2 py-1 text-[10px] font-semibold text-primary-50">
                        <component :is="netChangeUp ? TrendingUp : TrendingDown" class="size-3" /> {{ netChangeLabel }}
                    </span>
                </div>
                <p class="mt-3 text-[2rem] font-extrabold tracking-tight">{{ formatRupiah(props.summary.net_profit) }}</p>
                <p v-if="summaryCaption" class="mt-1 text-[10px] text-primary-100">{{ summaryCaption }}</p>
                <div class="mt-5 h-2 overflow-hidden rounded-full bg-primary-400/50" aria-label="Perbandingan pemasukan dan pengeluaran">
                    <div class="h-full rounded-full bg-emerald-300" :style="{ width: `${props.summary.income_ratio_percent}%` }" />
                </div>
                <div class="mt-2 flex justify-between text-[10px] text-primary-100">
                    <span>Pemasukan {{ formatRupiah(props.summary.income) }}</span>
                    <span>Pengeluaran {{ formatRupiah(props.summary.expense) }}</span>
                </div>
            </div>
        </section>

        <!-- US-MK-02: running-capital widget (Owner only) -->
        <section v-if="isOwner" class="mt-4" aria-label="Modal / kas usaha">
            <div v-if="props.capitalWidget" class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total modal periode ini</span>
                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                        Aktif s/d {{ formatDate(props.capitalWidget.end_date) }}
                    </span>
                </div>
                <div class="mt-1 text-xl font-bold tabular-nums tracking-tight">{{ formatRupiah(props.capitalWidget.period_total) }}</div>
                <div class="mt-2 text-xs text-slate-500">
                    Total modal saat ini:
                    <span
                        class="font-bold tabular-nums"
                        :class="props.capitalWidget.current_total < 0 ? 'text-rose-600' : 'text-slate-700'"
                    >{{ formatRupiah(props.capitalWidget.current_total) }}</span>
                </div>
            </div>
            <Link
                v-else
                :href="route('capital.index')"
                class="flex items-center justify-between rounded-2xl border border-dashed border-slate-300 bg-white p-4 text-sm font-medium text-slate-500 transition hover:border-primary-300 hover:text-primary-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
                Belum ada modal aktif
                <span class="text-xs font-bold text-primary-600">Set modal →</span>
            </Link>
        </section>

        <!-- US-SUB-01: Free-plan daily transaction quota, per type (AC1). Amber
             soft warning at 80% (AC2), rose at 100%. Hidden entirely for Paid. -->
        <section v-if="props.quotaWidget" class="mt-4 grid grid-cols-2 gap-2.5" aria-label="Kuota transaksi harian">
            <div
                v-for="item in quotaItems"
                :key="item.type"
                class="rounded-2xl border bg-white p-4"
                :class="item.reached ? 'border-rose-200' : item.nearLimit ? 'border-amber-200' : 'border-slate-200'"
            >
                <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{{ item.label }}</div>
                <div class="mt-1 text-xl font-bold tabular-nums tracking-tight">
                    {{ item.used }}<span class="text-sm font-semibold text-slate-400">/{{ props.quotaWidget.limit }}</span>
                </div>
                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100">
                    <div
                        class="h-full rounded-full"
                        :class="item.reached ? 'bg-rose-500' : item.nearLimit ? 'bg-amber-500' : 'bg-emerald-500'"
                        :style="{ width: `${item.percent}%` }"
                    />
                </div>
                <p v-if="item.reached" class="mt-2 text-xs font-semibold text-rose-600">
                    Kuota harian habis.
                    <Link v-if="isOwner" :href="route('subscription.index')" class="underline">Upgrade ke Paid</Link>
                </p>
                <p v-else-if="item.nearLimit" class="mt-2 text-xs font-semibold text-amber-600">
                    Sisa {{ item.remaining }} transaksi hari ini
                </p>
                <p v-else class="mt-2 text-xs text-slate-500">Sisa {{ item.remaining }} transaksi hari ini</p>
            </div>
        </section>

        <!-- US-INV-06: invoices not yet fully covered by linked transactions.
             Counts are on-the-fly (US-INV-04); hidden when nothing is outstanding. -->
        <Link
            v-if="props.invoiceReminderWidget"
            :href="route('invoices.index')"
            class="mt-6 flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-3.5 text-sm text-amber-800 transition hover:border-amber-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500"
            aria-label="Ringkasan invoice belum tuntas"
        >
            <span class="mt-0.5 text-base" aria-hidden="true">!</span>
            <div>
                <p class="font-bold">{{ props.invoiceReminderWidget.outstanding }} invoice belum lunas</p>
                <p v-if="props.invoiceReminderWidget.partial > 0" class="mt-0.5 text-xs text-amber-700">
                    {{ props.invoiceReminderWidget.partial }} invoice terpakai sebagian
                </p>
            </div>
            <ChevronRight class="ml-auto mt-0.5 size-4 shrink-0" />
        </Link>

        <section class="mt-6" aria-labelledby="quick-actions-title">
            <div class="mb-3 flex items-center justify-between">
                <h2 id="quick-actions-title" class="text-sm font-bold">Aksi cepat</h2>
                <span class="text-[10px] font-medium uppercase tracking-wider text-slate-400">MVP</span>
            </div>
            <div class="grid grid-cols-2 gap-2.5">
                <Link v-for="action in quickActions" :key="action.label" :href="action.href" class="group flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3 text-left transition hover:border-primary-200 hover:bg-primary-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 transition group-hover:bg-primary-100"><component :is="action.icon" class="size-[18px]" /></span>
                    <span class="text-xs font-semibold leading-tight text-slate-700">{{ action.label }}</span>
                </Link>
            </div>
        </section>

        <section class="mt-7 pb-4" aria-labelledby="recent-title">
            <div class="mb-2 flex items-center justify-between">
                <h2 id="recent-title" class="text-sm font-bold">Transaksi terbaru</h2>
                <Link :href="route('transactions.index')" class="text-xs font-bold text-primary-600 hover:text-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">Lihat semua <span aria-hidden="true">→</span></Link>
            </div>
            <div class="divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white px-3">
                <Link v-for="transaction in recentItems" :key="transaction.id" :href="route('transactions.show', transaction.id)" class="flex items-center gap-3 py-3.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500">
                    <span :class="transaction.type === 'income' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'" class="flex size-9 shrink-0 items-center justify-center rounded-xl"><ArrowUpRight v-if="transaction.type === 'income'" class="size-[18px]" /><ArrowDownLeft v-else class="size-[18px]" /></span>
                    <span class="min-w-0 flex-1"><strong class="block truncate text-xs font-bold text-slate-800">{{ transaction.label }}</strong><small class="mt-1 block text-[10px] text-slate-400">{{ transaction.date }}</small></span>
                    <span :class="transaction.type === 'income' ? 'text-emerald-600' : 'text-rose-600'" class="text-xs font-extrabold tabular-nums">{{ transaction.amount }}</span>
                    <ChevronRight class="size-4 text-slate-300" />
                </Link>
                <p v-if="recentItems.length === 0" class="py-6 text-center text-xs text-slate-400">Belum ada transaksi.</p>
            </div>
        </section>
    </PrototypeLayout>
</template>
