<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight } from '@lucide/vue';
import { computed, reactive } from 'vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';
import Card from '@/Components/ui/Card.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    report: {
        type: Object,
        required: true,
    },
});

const periods = [
    { value: 'today', label: 'Hari ini' },
    { value: 'week', label: 'Minggu ini' },
    { value: 'month', label: 'Bulan ini' },
    { value: 'custom', label: 'Custom' },
];

const selectedPeriod = computed(() => props.report.period);
const customRange = reactive({
    from: props.report.date_from,
    to: props.report.date_to,
});

const periodLabel = computed(() => props.report.period_label);
const formatRupiah = (value) => `Rp${Number(value || 0).toLocaleString('id-ID')}`;

const selectPeriod = (period) => {
    if (period === 'custom') {
        return;
    }

    router.get(route('reports.profit-loss'), { period }, {
        preserveState: true,
        replace: true,
    });
};

const applyCustomRange = () => {
    router.get(route('reports.profit-loss'), {
        period: 'custom',
        date_from: customRange.from,
        date_to: customRange.to,
    }, {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <Head title="Laporan P&L" />

    <PrototypeLayout>
        <section class="pb-5 pt-4">
            <PageHeader title="Laporan P&L" :back="route('dashboard')" />
        </section>

        <div class="flex rounded-xl bg-slate-100 p-1" role="tablist" aria-label="Filter periode">
            <button
                v-for="period in periods"
                :key="period.value"
                type="button"
                role="tab"
                :aria-selected="selectedPeriod === period.value"
                :class="selectedPeriod === period.value ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                class="flex-1 rounded-lg px-3 py-2 text-xs font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                @click="selectPeriod(period.value)"
            >
                {{ period.label }}
            </button>
        </div>

        <form v-if="selectedPeriod === 'custom'" class="mt-3 grid grid-cols-2 gap-3" @submit.prevent="applyCustomRange">
            <div>
                <label for="range-from" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Dari</label>
                <input id="range-from" v-model="customRange.from" required type="date" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-primary-500 focus:ring-primary-500" />
            </div>
            <div>
                <label for="range-to" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Sampai</label>
                <input id="range-to" v-model="customRange.to" required type="date" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-primary-500 focus:ring-primary-500" />
            </div>
            <button type="submit" class="col-span-2 rounded-xl bg-primary-600 px-4 py-3 text-xs font-bold text-white transition hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">Terapkan</button>
        </form>

        <p class="mt-4 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Periode {{ periodLabel }}</p>

        <section class="mt-2 grid grid-cols-2 gap-3" aria-label="Ringkasan laba rugi">
            <Card label="Pemasukan" :amount="formatRupiah(props.report.income)">
                <div class="mt-2 flex items-center gap-1 text-xs font-bold text-emerald-600">
                    <ArrowUpRight class="size-4" /> Masuk
                </div>
            </Card>
            <Card label="Pengeluaran" :amount="formatRupiah(props.report.expense)">
                <div class="mt-2 flex items-center gap-1 text-xs font-bold text-rose-600">
                    <ArrowDownLeft class="size-4" /> Keluar
                </div>
            </Card>
        </section>

        <section class="mt-3" aria-label="Saldo bersih">
            <div class="rounded-2xl border border-primary-200 bg-primary-50 p-4">
                <div class="text-[10px] font-extrabold uppercase tracking-wider text-primary-500">Saldo bersih</div>
                <div class="mt-1 text-2xl font-extrabold tabular-nums tracking-tight text-primary-700">{{ formatRupiah(props.report.net) }}</div>
                <p class="mt-1 text-xs text-primary-500">Pemasukan dikurangi pengeluaran pada periode ini</p>
            </div>
        </section>

        <section class="mt-6" aria-labelledby="income-breakdown-title">
            <h2 id="income-breakdown-title" class="mb-2 text-sm font-bold">Breakdown pemasukan</h2>
            <div class="rounded-2xl border border-slate-200 bg-white px-4">
                <div v-for="row in props.report.incomeBreakdown" :key="row.label" class="border-b border-slate-100 py-3.5 last:border-b-0">
                    <div class="flex items-center justify-between gap-4 text-xs">
                        <span class="text-slate-600">{{ row.label }}</span>
                        <strong class="font-semibold tabular-nums text-slate-800">{{ row.amount }}</strong>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-emerald-500" :style="{ width: `${row.percent}%` }" />
                    </div>
                </div>
            </div>
        </section>

        <section class="mt-4 pb-8" aria-labelledby="expense-breakdown-title">
            <h2 id="expense-breakdown-title" class="mb-2 text-sm font-bold">Breakdown pengeluaran</h2>
            <div class="rounded-2xl border border-slate-200 bg-white px-4">
                <div v-for="row in props.report.expenseBreakdown" :key="row.label" class="border-b border-slate-100 py-3.5 last:border-b-0">
                    <div class="flex items-center justify-between gap-4 text-xs">
                        <span class="text-slate-600">{{ row.label }}</span>
                        <strong class="font-semibold tabular-nums text-slate-800">{{ row.amount }}</strong>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-rose-500" :style="{ width: `${row.percent}%` }" />
                    </div>
                </div>
            </div>
        </section>
    </PrototypeLayout>
</template>
