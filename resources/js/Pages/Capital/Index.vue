<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import { ref } from 'vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';

const props = defineProps({
    capital: {
        type: Object,
        default: () => ({
            active: false,
            total: 'Rp3.000.000',
            period: '14 Agu – 13 Sep 2026',
        }),
    },
});

const durations = ['1 Minggu', '1 Bulan'];
const form = ref({ nominal: '3000000', duration: '1 Bulan' });
const active = ref(props.capital.active);
const total = ref(props.capital.total);

const topUpOpen = ref(false);
const topUpSaved = ref(false);
const topUp = ref({ amount: '2500000', method: 'Transfer Bank' });

const saveCapital = () => {
    active.value = true;
};

const closeTopUp = () => {
    topUpOpen.value = false;
};

const saveTopUp = () => {
    topUpOpen.value = false;
    topUpSaved.value = true;
    total.value = 'Rp5.500.000';
};
</script>

<template>
    <Head title="Modal / Kas Usaha" />

    <PrototypeLayout>
        <section class="flex items-center gap-3 pb-5 pt-4">
            <Link
                :href="route('dashboard')"
                aria-label="Kembali ke beranda"
                class="flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:border-indigo-200 hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <ChevronLeft class="size-5" />
            </Link>
            <h1 class="text-xl font-bold tracking-tight">Modal / Kas Usaha</h1>
        </section>

        <template v-if="!active">
            <p class="text-sm text-slate-500">Belum ada modal aktif — set dulu sebagai baseline.</p>

            <form class="mt-4 space-y-4 pb-8" @submit.prevent="saveCapital">
                <div>
                    <label for="capital-nominal" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Nominal modal</label>
                    <input id="capital-nominal" v-model="form.nominal" type="number" min="1" required class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-indigo-500 focus:ring-indigo-500" />
                </div>
                <div>
                    <span class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Masa berlaku</span>
                    <div class="flex rounded-xl bg-slate-100 p-1" role="group" aria-label="Masa berlaku modal">
                        <button
                            v-for="duration in durations"
                            :key="duration"
                            type="button"
                            :aria-pressed="form.duration === duration"
                            :class="form.duration === duration ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                            class="flex-1 rounded-lg px-3 py-2 text-xs font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            @click="form.duration = duration"
                        >
                            {{ duration }}
                        </button>
                    </div>
                </div>
                <button type="submit" class="flex min-h-12 w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-sm shadow-indigo-200 transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">
                    Simpan modal
                </button>
            </form>
        </template>

        <template v-else>
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total modal saat ini</span>
                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-600">Aktif</span>
                </div>
                <div class="mt-1 text-xl font-bold tabular-nums tracking-tight">{{ total }}</div>
                <div class="mt-2 text-xs text-slate-500">Periode {{ props.capital.period }}</div>
            </div>

            <div v-if="topUpSaved" class="mt-4 flex items-center rounded-2xl border border-emerald-200 bg-emerald-50 p-3.5 text-sm font-semibold text-emerald-700" role="status">
                Top-up Rp2.500.000 tersimpan.
            </div>

            <div class="grid gap-3 pb-8 pt-5">
                <button
                    type="button"
                    class="flex min-h-12 w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-sm shadow-indigo-200 transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                    @click="topUpOpen = true"
                >
                    Tambah top-up
                </button>
                <Link
                    :href="route('dashboard')"
                    class="flex min-h-12 w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                >
                    Kembali ke beranda
                </Link>
            </div>
        </template>

        <div v-if="topUpOpen" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center" role="presentation" @click.self="closeTopUp">
            <section class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="topup-title">
                <h2 id="topup-title" class="text-lg font-bold text-slate-800">Tambah top-up modal</h2>
                <form class="mt-4 space-y-4" @submit.prevent="saveTopUp">
                    <div>
                        <label for="topup-amount" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Nominal</label>
                        <input id="topup-amount" v-model="topUp.amount" type="number" min="1" required class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label for="topup-method" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Metode</label>
                        <select id="topup-method" v-model="topUp.method" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-indigo-500 focus:ring-indigo-500">
                            <option>Transfer Bank</option>
                            <option>Cash</option>
                            <option>E-Wallet</option>
                        </select>
                    </div>
                    <div class="flex gap-3 pt-1">
                        <button type="button" class="flex min-h-11 flex-1 items-center justify-center rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500" @click="closeTopUp">Batal</button>
                        <button type="submit" class="flex min-h-11 flex-1 items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">Simpan</button>
                    </div>
                </form>
            </section>
        </div>
    </PrototypeLayout>
</template>
