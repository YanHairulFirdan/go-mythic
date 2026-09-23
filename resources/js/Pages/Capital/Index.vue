<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { driver } from 'driver.js';
import { ChevronLeft, Pencil, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import { formatRupiah } from '@/utils/currency';

const props = defineProps({
    activeEntry: {
        type: Object,
        default: null,
    },
});

const durations = [
    { value: '1_year', label: '1 Tahun' },
    { value: 'no_end', label: 'Tanpa batas' },
    { value: 'custom', label: 'Custom' },
];

const durationHints = {
    '1_year': 'Mulai hari ini, berakhir otomatis satu tahun kemudian.',
    no_end: 'Mulai hari ini, tanpa tanggal selesai — jalan terus sampai diubah.',
};

const form = useForm({
    duration: '1_year',
    initial_amount: null,
    start_date: '',
    end_date: '',
});

const isCustom = computed(() => form.duration === 'custom');

const formatDate = (value) => (value
    ? new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
    : '');

const submit = () => form.post(route('capital.store'));

// --- Top-up (US-MK-01B) ---
const topUpOpen = ref(false);
const topUpForm = useForm({ amount: null, extended_end_date: '' });

const periodTotalPreview = computed(
    () => Number(props.activeEntry?.period_total || 0) + (Number(topUpForm.amount) || 0),
);

const currentTotalPreview = computed(
    () => Number(props.activeEntry?.current_total || 0) + (Number(topUpForm.amount) || 0),
);

const minExtendDate = computed(() => {
    if (!props.activeEntry?.end_date) return undefined;
    const next = new Date(props.activeEntry.end_date);
    next.setDate(next.getDate() + 1);
    return next.toISOString().slice(0, 10);
});

const openTopUp = () => {
    topUpForm.reset();
    topUpForm.clearErrors();
    topUpOpen.value = true;
};

const submitTopUp = () => {
    topUpForm.patch(route('capital.top-up', props.activeEntry.id), {
        preserveScroll: true,
        onSuccess: () => {
            topUpOpen.value = false;
        },
    });
};

// --- Edit entry ---
const editOpen = ref(false);
const editForm = useForm({ initial_amount: null, start_date: '', end_date: '', no_end: false });

const openEdit = () => {
    editForm.clearErrors();
    editForm.initial_amount = props.activeEntry.initial_amount;
    editForm.start_date = props.activeEntry.start_date;
    editForm.end_date = props.activeEntry.end_date ?? '';
    editForm.no_end = props.activeEntry.end_date === null;
    editOpen.value = true;
};

const submitEdit = () => {
    editForm
        .transform((data) => ({
            initial_amount: data.initial_amount,
            start_date: data.start_date,
            end_date: data.no_end ? null : data.end_date,
        }))
        .patch(route('capital.update', props.activeEntry.id), {
            preserveScroll: true,
            onSuccess: () => {
                editOpen.value = false;
            },
        });
};

// --- Delete entry ---
const confirmingDelete = ref(false);
const deleting = ref(false);

const destroy = () => {
    deleting.value = true;
    router.delete(route('capital.destroy', props.activeEntry.id), {
        onFinish: () => {
            deleting.value = false;
            confirmingDelete.value = false;
        },
    });
};

// skipMissingElement: true, karena target berbeda tergantung ada/tidaknya activeEntry.
const startTour = () => {
    driver({
        showProgress: true,
        animate: false,
        skipMissingElement: true,
        nextBtnText: 'Lanjut',
        prevBtnText: 'Kembali',
        doneBtnText: 'Selesai',
        steps: [
            { element: '[data-tour="capital-amount"]', popover: { title: 'Nominal modal', description: 'Masukkan modal awal sebagai baseline perhitungan.' } },
            { element: '[data-tour="capital-duration"]', popover: { title: 'Masa berlaku', description: 'Tentukan berapa lama periode modal ini berlaku.' } },
            { element: '[data-tour="capital-summary"]', popover: { title: 'Ringkasan modal', description: 'Lihat total modal periode ini dan saat ini.' } },
            { element: '[data-tour="capital-topup"]', popover: { title: 'Top-up modal', description: 'Tambahkan modal tambahan kapan saja.' } },
            { element: '[data-tour="capital-actions"]', popover: { title: 'Edit / hapus', description: 'Ubah atau hapus modal yang sudah diatur.' } },
            { element: '[data-tour="capital-history"]', popover: { title: 'Riwayat modal', description: 'Lihat catatan modal dan top-up sebelumnya.' } },
        ],
    }).drive();
};
</script>

<template>
    <Head title="Modal / Kas Usaha" />

    <PrototypeLayout>
        <section class="flex items-center gap-3 pb-5 pt-4">
            <Link
                :href="route('dashboard')"
                aria-label="Kembali ke beranda"
                class="flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:border-primary-200 hover:text-primary-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
                <ChevronLeft class="size-5" />
            </Link>
            <h1 class="flex-1 text-xl font-bold tracking-tight">Modal / Kas Usaha</h1>
            <button
                type="button"
                class="text-xs font-bold text-primary-700 underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                @click="startTour"
            >
                Mulai tur
            </button>
        </section>

        <template v-if="!props.activeEntry">
            <p class="text-sm text-slate-500">Belum ada modal aktif — set dulu sebagai baseline.</p>

            <form class="mt-4 space-y-4 pb-8" @submit.prevent="submit">
                <div data-tour="capital-amount">
                    <label for="initial_amount" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Nominal modal</label>
                    <CurrencyInput id="initial_amount" v-model="form.initial_amount" required placeholder="0" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 placeholder:text-slate-300 focus:border-primary-500 focus:ring-primary-500" />
                    <p v-if="form.errors.initial_amount" class="mt-1.5 text-xs font-semibold text-rose-600">{{ form.errors.initial_amount }}</p>
                </div>

                <div data-tour="capital-duration">
                    <span class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Masa berlaku</span>
                    <div class="grid grid-cols-3 gap-1 rounded-xl bg-slate-100 p-1" role="group" aria-label="Masa berlaku modal">
                        <button
                            v-for="duration in durations"
                            :key="duration.value"
                            type="button"
                            :aria-pressed="form.duration === duration.value"
                            :class="form.duration === duration.value ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                            class="rounded-lg px-2 py-2 text-xs font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            @click="form.duration = duration.value"
                        >
                            {{ duration.label }}
                        </button>
                    </div>
                    <p v-if="form.errors.duration" class="mt-1.5 text-xs font-semibold text-rose-600">{{ form.errors.duration }}</p>
                    <p v-if="durationHints[form.duration]" class="mt-1.5 text-xs text-slate-400">{{ durationHints[form.duration] }}</p>
                </div>

                <div v-if="isCustom" class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="start_date" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Mulai</label>
                        <input id="start_date" v-model="form.start_date" type="date" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-primary-500 focus:ring-primary-500" />
                        <p v-if="form.errors.start_date" class="mt-1.5 text-xs font-semibold text-rose-600">{{ form.errors.start_date }}</p>
                    </div>
                    <div>
                        <label for="end_date" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Selesai</label>
                        <input id="end_date" v-model="form.end_date" type="date" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-primary-500 focus:ring-primary-500" />
                        <p v-if="form.errors.end_date" class="mt-1.5 text-xs font-semibold text-rose-600">{{ form.errors.end_date }}</p>
                    </div>
                </div>

                <button type="submit" :disabled="form.processing" class="flex min-h-12 w-full items-center justify-center rounded-xl bg-primary-600 px-4 py-3 text-sm font-bold text-white shadow-sm shadow-primary-200 transition hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 disabled:opacity-50">
                    {{ form.processing ? 'Menyimpan…' : 'Simpan modal' }}
                </button>

                <Link
                    data-tour="capital-history"
                    :href="route('capital.history')"
                    class="flex min-h-12 w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                >
                    Riwayat modal
                </Link>
            </form>
        </template>

        <template v-else>
            <div data-tour="capital-summary" class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total modal periode ini</span>
                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-600">Aktif</span>
                </div>
                <div class="mt-1 text-xl font-bold tabular-nums tracking-tight">{{ formatRupiah(props.activeEntry.period_total) }}</div>
                <div class="mt-3 flex items-baseline justify-between border-t border-slate-100 pt-3">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total modal saat ini</span>
                    <span
                        class="text-sm font-bold tabular-nums"
                        :class="props.activeEntry.current_total < 0 ? 'text-rose-600' : 'text-slate-800'"
                    >{{ formatRupiah(props.activeEntry.current_total) }}</span>
                </div>
                <div class="mt-2 text-xs text-slate-500">
                    Periode {{ formatDate(props.activeEntry.start_date) }} – {{ props.activeEntry.end_date ? formatDate(props.activeEntry.end_date) : 'tanpa batas' }}
                </div>
            </div>

            <div class="grid gap-3 pb-8 pt-5">
                <button
                    data-tour="capital-topup"
                    type="button"
                    class="flex min-h-12 w-full items-center justify-center rounded-xl bg-primary-600 px-4 py-3 text-sm font-bold text-white shadow-sm shadow-primary-200 transition hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2"
                    @click="openTopUp"
                >
                    Top-up Modal
                </button>
                <div data-tour="capital-actions" class="grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        class="flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                        @click="openEdit"
                    >
                        <Pencil class="size-4" /> Edit modal
                    </button>
                    <button
                        type="button"
                        class="flex min-h-11 items-center justify-center gap-2 rounded-xl border border-rose-200 bg-white px-4 py-3 text-sm font-semibold text-rose-600 transition hover:bg-rose-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500"
                        @click="confirmingDelete = true"
                    >
                        <Trash2 class="size-4" /> Hapus
                    </button>
                </div>
                <Link
                    data-tour="capital-history"
                    :href="route('capital.history')"
                    class="flex min-h-12 w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                >
                    Riwayat modal
                </Link>
            </div>

            <div
                v-if="topUpOpen"
                class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center"
                role="presentation"
                @click.self="topUpOpen = false"
            >
                <section class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="topup-title">
                    <h2 id="topup-title" class="text-lg font-bold text-slate-800">Top-up modal</h2>

                    <form class="mt-4 space-y-4" @submit.prevent="submitTopUp">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-xl bg-slate-50 p-3">
                                <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Periode ini</div>
                                <div class="mt-1 text-sm font-bold tabular-nums text-slate-800">{{ formatRupiah(periodTotalPreview) }}</div>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-3">
                                <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Saat ini</div>
                                <div
                                    class="mt-1 text-sm font-bold tabular-nums"
                                    :class="currentTotalPreview < 0 ? 'text-rose-600' : 'text-slate-800'"
                                >
                                    {{ formatRupiah(currentTotalPreview) }}
                                </div>
                            </div>
                        </div>

                        <div>
                            <label for="topup-amount" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Tambahan modal</label>
                            <CurrencyInput id="topup-amount" v-model="topUpForm.amount" required placeholder="0" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 placeholder:text-slate-300 focus:border-primary-500 focus:ring-primary-500" />
                            <p v-if="topUpForm.errors.amount" class="mt-1.5 text-xs font-semibold text-rose-600">{{ topUpForm.errors.amount }}</p>
                        </div>

                        <div>
                            <label for="topup-extend" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Perpanjang tanggal selesai <span class="font-medium normal-case tracking-normal text-slate-400">(opsional)</span></label>
                            <input id="topup-extend" v-model="topUpForm.extended_end_date" type="date" :min="minExtendDate" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-primary-500 focus:ring-primary-500" />
                            <p v-if="topUpForm.errors.extended_end_date" class="mt-1.5 text-xs font-semibold text-rose-600">{{ topUpForm.errors.extended_end_date }}</p>
                        </div>

                        <div class="flex gap-3 pt-1">
                            <button type="button" class="flex min-h-11 flex-1 items-center justify-center rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500" @click="topUpOpen = false">Batal</button>
                            <button type="submit" :disabled="topUpForm.processing" class="flex min-h-11 flex-1 items-center justify-center rounded-xl bg-primary-600 px-4 py-3 text-sm font-bold text-white hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:opacity-50">{{ topUpForm.processing ? 'Menyimpan…' : 'Simpan' }}</button>
                        </div>
                    </form>
                </section>
            </div>

            <div
                v-if="editOpen"
                class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center"
                role="presentation"
                @click.self="editOpen = false"
            >
                <section class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="edit-title">
                    <h2 id="edit-title" class="text-lg font-bold text-slate-800">Edit modal</h2>

                    <form class="mt-4 space-y-4" @submit.prevent="submitEdit">
                        <div>
                            <label for="edit-amount" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Nominal modal</label>
                            <CurrencyInput id="edit-amount" v-model="editForm.initial_amount" required placeholder="0" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 placeholder:text-slate-300 focus:border-primary-500 focus:ring-primary-500" />
                            <p v-if="editForm.errors.initial_amount" class="mt-1.5 text-xs font-semibold text-rose-600">{{ editForm.errors.initial_amount }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="edit-start" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Mulai</label>
                                <input id="edit-start" v-model="editForm.start_date" type="date" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-primary-500 focus:ring-primary-500" />
                                <p v-if="editForm.errors.start_date" class="mt-1.5 text-xs font-semibold text-rose-600">{{ editForm.errors.start_date }}</p>
                            </div>
                            <div>
                                <label for="edit-end" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Selesai</label>
                                <input id="edit-end" v-model="editForm.end_date" type="date" :disabled="editForm.no_end" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-primary-500 focus:ring-primary-500 disabled:bg-slate-100 disabled:text-slate-400" />
                                <p v-if="editForm.errors.end_date" class="mt-1.5 text-xs font-semibold text-rose-600">{{ editForm.errors.end_date }}</p>
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                            <input v-model="editForm.no_end" type="checkbox" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                            Tanpa tanggal selesai
                        </label>

                        <p class="text-xs text-slate-400">Mengubah nominal atau periode langsung memengaruhi total modal & laporan.</p>

                        <div class="flex gap-3 pt-1">
                            <button type="button" class="flex min-h-11 flex-1 items-center justify-center rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500" @click="editOpen = false">Batal</button>
                            <button type="submit" :disabled="editForm.processing" class="flex min-h-11 flex-1 items-center justify-center rounded-xl bg-primary-600 px-4 py-3 text-sm font-bold text-white hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:opacity-50">{{ editForm.processing ? 'Menyimpan…' : 'Simpan' }}</button>
                        </div>
                    </form>
                </section>
            </div>

            <div
                v-if="confirmingDelete"
                class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center"
                role="presentation"
                @click.self="confirmingDelete = false"
            >
                <section class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="delete-capital-title">
                    <h2 id="delete-capital-title" class="text-sm font-bold text-slate-900">Hapus modal ini?</h2>
                    <p class="mt-1.5 text-xs text-slate-500">
                        Periode {{ formatDate(props.activeEntry.start_date) }} – {{ props.activeEntry.end_date ? formatDate(props.activeEntry.end_date) : 'tanpa batas' }} akan dihapus. Transaksi yang sudah tercatat tetap tersimpan.
                    </p>
                    <div class="mt-4 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                            @click="confirmingDelete = false"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            :disabled="deleting"
                            class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-rose-700 disabled:opacity-50"
                            @click="destroy"
                        >
                            {{ deleting ? 'Menghapus…' : 'Hapus' }}
                        </button>
                    </div>
                </section>
            </div>
        </template>
    </PrototypeLayout>
</template>
