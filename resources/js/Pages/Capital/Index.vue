<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import { computed } from 'vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';

const props = defineProps({
    activeEntry: {
        type: Object,
        default: null,
    },
});

const durations = [
    { value: '1_day', label: '1 Hari' },
    { value: '1_week', label: '1 Minggu' },
    { value: '1_month', label: '1 Bulan' },
    { value: 'custom', label: 'Custom' },
];

const form = useForm({
    duration: '1_month',
    initial_amount: null,
    start_date: '',
    end_date: '',
});

const isCustom = computed(() => form.duration === 'custom');

const formatRupiah = (value) => `Rp${Number(value || 0).toLocaleString('id-ID')}`;
const formatDate = (value) => (value
    ? new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
    : '');

const submit = () => form.post(route('capital.store'));
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

        <template v-if="!props.activeEntry">
            <p class="text-sm text-slate-500">Belum ada modal aktif — set dulu sebagai baseline.</p>

            <form class="mt-4 space-y-4 pb-8" @submit.prevent="submit">
                <div>
                    <label for="initial_amount" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Nominal modal</label>
                    <input id="initial_amount" v-model.number="form.initial_amount" type="number" min="1" step="any" required placeholder="0" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 placeholder:text-slate-300 focus:border-indigo-500 focus:ring-indigo-500" />
                    <p v-if="form.errors.initial_amount" class="mt-1.5 text-xs font-semibold text-rose-600">{{ form.errors.initial_amount }}</p>
                </div>

                <div>
                    <span class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Masa berlaku</span>
                    <div class="grid grid-cols-4 gap-1 rounded-xl bg-slate-100 p-1" role="group" aria-label="Masa berlaku modal">
                        <button
                            v-for="duration in durations"
                            :key="duration.value"
                            type="button"
                            :aria-pressed="form.duration === duration.value"
                            :class="form.duration === duration.value ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                            class="rounded-lg px-2 py-2 text-xs font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            @click="form.duration = duration.value"
                        >
                            {{ duration.label }}
                        </button>
                    </div>
                    <p v-if="form.errors.duration" class="mt-1.5 text-xs font-semibold text-rose-600">{{ form.errors.duration }}</p>
                    <p v-if="!isCustom" class="mt-1.5 text-xs text-slate-400">Mulai hari ini, berakhir otomatis sesuai durasi.</p>
                </div>

                <div v-if="isCustom" class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="start_date" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Mulai</label>
                        <input id="start_date" v-model="form.start_date" type="date" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-indigo-500 focus:ring-indigo-500" />
                        <p v-if="form.errors.start_date" class="mt-1.5 text-xs font-semibold text-rose-600">{{ form.errors.start_date }}</p>
                    </div>
                    <div>
                        <label for="end_date" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Selesai</label>
                        <input id="end_date" v-model="form.end_date" type="date" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-indigo-500 focus:ring-indigo-500" />
                        <p v-if="form.errors.end_date" class="mt-1.5 text-xs font-semibold text-rose-600">{{ form.errors.end_date }}</p>
                    </div>
                </div>

                <button type="submit" :disabled="form.processing" class="flex min-h-12 w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-sm shadow-indigo-200 transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:opacity-50">
                    Simpan modal
                </button>
            </form>
        </template>

        <template v-else>
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total modal periode ini</span>
                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-600">Aktif</span>
                </div>
                <div class="mt-1 text-xl font-bold tabular-nums tracking-tight">{{ formatRupiah(props.activeEntry.initial_amount) }}</div>
                <div class="mt-2 text-xs text-slate-500">
                    Periode {{ formatDate(props.activeEntry.start_date) }} – {{ formatDate(props.activeEntry.end_date) }}
                </div>
            </div>

            <div class="pb-8 pt-5">
                <button
                    type="button"
                    disabled
                    class="flex min-h-12 w-full cursor-not-allowed items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-400"
                >
                    Top-up Modal
                </button>
                <p class="mt-1.5 text-center text-xs text-slate-400">Top-up modal tersedia di rilis berikutnya.</p>
            </div>
        </template>
    </PrototypeLayout>
</template>
