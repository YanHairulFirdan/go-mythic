<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import { computed, ref } from 'vue';
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
const paymentOpen = ref(false);
const paymentSaved = ref(false);
const payment = ref({
    amount: '650000',
    method: 'Transfer Bank',
    date: '2026-08-14',
});

const closePayment = () => {
    paymentOpen.value = false;
};

const savePayment = () => {
    paymentOpen.value = false;
    paymentSaved.value = true;
};
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

        <div v-if="paymentSaved" class="mt-4 flex items-center rounded-2xl border border-emerald-200 bg-emerald-50 p-3.5 text-sm font-semibold text-emerald-700" role="status">
            Pembayaran tersimpan.
        </div>

        <div class="pb-8 pt-5">
            <button
                type="button"
                :disabled="props.invoice.settled"
                class="flex min-h-12 w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-sm shadow-indigo-200 transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500 disabled:shadow-none"
                @click="paymentOpen = true"
            >
                {{ props.invoice.settled ? 'Pembayaran lunas' : '+ Catat pembayaran' }}
            </button>
        </div>

        <div v-if="paymentOpen" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center" role="presentation" @click.self="closePayment">
            <section class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="payment-title">
                <h2 id="payment-title" class="text-lg font-bold text-slate-800">Catat pembayaran</h2>
                <form class="mt-4 space-y-4" @submit.prevent="savePayment">
                    <div>
                        <label for="payment-amount" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Nominal</label>
                        <input id="payment-amount" v-model="payment.amount" type="number" min="1" required class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label for="payment-method" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Metode pembayaran</label>
                        <select id="payment-method" v-model="payment.method" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-indigo-500 focus:ring-indigo-500">
                            <option>Transfer Bank</option>
                            <option>Cash</option>
                            <option>E-Wallet</option>
                        </select>
                    </div>
                    <div>
                        <label for="payment-date" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Tanggal</label>
                        <input id="payment-date" v-model="payment.date" type="date" required class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>
                    <div class="flex gap-3 pt-1">
                        <button type="button" class="flex min-h-11 flex-1 items-center justify-center rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500" @click="closePayment">Batal</button>
                        <button type="submit" class="flex min-h-11 flex-1 items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">Simpan pembayaran</button>
                    </div>
                </form>
            </section>
        </div>
    </PrototypeLayout>
</template>
