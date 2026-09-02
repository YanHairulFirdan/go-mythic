<script setup>
import { Head } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight } from '@lucide/vue';
import { computed, ref } from 'vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';
import Card from '@/Components/ui/Card.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    report: {
        type: Object,
        default: () => ({
            period: 'Agustus 2026',
            income: 'Rp7.100.000',
            expense: 'Rp2.850.000',
            net: 'Rp4.250.000',
            incomeBreakdown: [
                { label: 'Jasa cleaning rumah', amount: 'Rp4.200.000', percent: 59 },
                { label: 'Jasa cleaning kantor', amount: 'Rp2.100.000', percent: 30 },
                { label: 'Lainnya', amount: 'Rp800.000', percent: 11 },
            ],
            expenseBreakdown: [
                { label: 'Gaji karyawan', amount: 'Rp1.500.000', percent: 53 },
                { label: 'Perlengkapan & bahan', amount: 'Rp850.000', percent: 30 },
                { label: 'Transportasi', amount: 'Rp300.000', percent: 10 },
                { label: 'Lainnya', amount: 'Rp200.000', percent: 7 },
            ],
        }),
    },
});

const periods = ['Bulan ini', 'Custom'];
const selectedPeriod = ref('Bulan ini');
const customRange = ref({ from: '2026-08-01', to: '2026-08-31' });

const periodLabel = computed(() => (selectedPeriod.value === 'Custom'
    ? `${customRange.value.from} — ${customRange.value.to}`
    : props.report.period));
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
                :key="period"
                type="button"
                role="tab"
                :aria-selected="selectedPeriod === period"
                :class="selectedPeriod === period ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                class="flex-1 rounded-lg px-3 py-2 text-xs font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                @click="selectedPeriod = period"
            >
                {{ period }}
            </button>
        </div>

        <div v-if="selectedPeriod === 'Custom'" class="mt-3 grid grid-cols-2 gap-3">
            <div>
                <label for="range-from" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Dari</label>
                <input id="range-from" v-model="customRange.from" type="date" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-primary-500 focus:ring-primary-500" />
            </div>
            <div>
                <label for="range-to" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Sampai</label>
                <input id="range-to" v-model="customRange.to" type="date" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-primary-500 focus:ring-primary-500" />
            </div>
        </div>

        <p class="mt-4 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Periode {{ periodLabel }}</p>

        <section class="mt-2 grid grid-cols-2 gap-3" aria-label="Ringkasan laba rugi">
            <Card label="Pemasukan" :amount="props.report.income">
                <div class="mt-2 flex items-center gap-1 text-xs font-bold text-emerald-600">
                    <ArrowUpRight class="size-4" /> Masuk
                </div>
            </Card>
            <Card label="Pengeluaran" :amount="props.report.expense">
                <div class="mt-2 flex items-center gap-1 text-xs font-bold text-rose-600">
                    <ArrowDownLeft class="size-4" /> Keluar
                </div>
            </Card>
        </section>

        <section class="mt-3" aria-label="Saldo bersih">
            <div class="rounded-2xl border border-primary-200 bg-primary-50 p-4">
                <div class="text-[10px] font-extrabold uppercase tracking-wider text-primary-500">Saldo bersih</div>
                <div class="mt-1 text-2xl font-extrabold tabular-nums tracking-tight text-primary-700">{{ props.report.net }}</div>
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
