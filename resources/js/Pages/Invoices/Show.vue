<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import { computed } from 'vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';
import Card from '@/Components/ui/Card.vue';

const props = defineProps({
    invoice: {
        type: Object,
        default: () => ({
            number: 'INV-0042',
            customer: 'Toko Bintang',
            assignee: 'Made Wirawan',
            items: [
                { name: 'Cleaning ruang tamu & dapur', amount: 'Rp900.000' },
                { name: 'Cleaning 2 kamar mandi', amount: 'Rp600.000' },
            ],
            paid: 'Rp850.000',
            total: 'Rp1.500.000',
            percent: 57,
            settled: false,
        }),
    },
});

const balance = computed(() => (props.invoice.settled ? 'Lunas' : 'Sisa Rp650.000'));
</script>

<template>
    <Head title="Detail invoice" />

    <PrototypeLayout>
        <section class="flex items-center gap-3 pb-5 pt-4">
            <Link
                :href="route('invoices.index')"
                aria-label="Kembali ke invoice"
                class="flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:border-indigo-200 hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <ChevronLeft class="size-5" />
            </Link>
            <h1 class="text-xl font-bold tracking-tight">{{ props.invoice.number }}</h1>
        </section>

        <Card label="Customer" :amount="props.invoice.customer">
            <p class="mt-1 text-xs text-slate-500">Penanggung jawab: {{ props.invoice.assignee }}</p>
        </Card>

        <section class="mt-4" aria-labelledby="items-title">
            <h2 id="items-title" class="mb-2 text-sm font-bold">Rincian item</h2>
            <div class="rounded-2xl border border-slate-200 bg-white px-4">
                <div v-for="item in props.invoice.items" :key="item.name" class="flex items-center justify-between gap-4 border-b border-slate-100 py-3.5 text-xs last:border-b-0">
                    <span class="text-slate-600">{{ item.name }}</span>
                    <strong class="font-semibold tabular-nums text-slate-800">{{ item.amount }}</strong>
                </div>
            </div>
        </section>

        <section class="mt-4">
            <Card label="Progress pembayaran" :amount="`${props.invoice.paid} / ${props.invoice.total}`">
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100" aria-label="Progress pembayaran invoice">
                    <div class="h-full rounded-full bg-emerald-500" :style="{ width: `${props.invoice.percent}%` }" />
                </div>
                <p class="mt-2 text-xs font-semibold" :class="props.invoice.settled ? 'text-emerald-600' : 'text-amber-600'">{{ balance }}</p>
            </Card>
        </section>

        <div class="pb-8 pt-5">
            <Link
                href="#invoice-payment"
                class="flex min-h-12 w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-sm shadow-indigo-200 transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
            >
                {{ props.invoice.settled ? 'Pembayaran lunas' : '+ Catat pembayaran' }}
            </Link>
        </div>
    </PrototypeLayout>
</template>
