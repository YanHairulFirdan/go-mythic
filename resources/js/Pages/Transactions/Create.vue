<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight, Check, ChevronLeft } from '@lucide/vue';
import { computed, ref } from 'vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';
import Card from '@/Components/ui/Card.vue';

const type = ref('income');
const saved = ref(false);
const form = ref({
    amount: '',
    category: 'Jasa Cleaning Rumah',
    paymentMethod: 'Transfer Bank',
    date: '2026-08-14',
    customer: '',
    invoice: '',
    note: '',
});

const isIncome = computed(() => type.value === 'income');
const quota = computed(() => isIncome.value ? '42/150' : '18/150');

const resetNotice = () => {
    saved.value = false;
};

const submit = () => {
    saved.value = true;
};
</script>

<template>
    <Head title="Tambah transaksi" />

    <PrototypeLayout>
        <section class="flex items-center gap-3 pb-5 pt-4">
            <Link
                :href="route('transactions.index')"
                aria-label="Kembali ke transaksi"
                class="flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:border-indigo-200 hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <ChevronLeft class="size-5" />
            </Link>
            <h1 class="text-xl font-bold tracking-tight">Tambah transaksi</h1>
        </section>

        <div v-if="saved" class="mb-4 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 p-3.5 text-sm font-semibold text-emerald-700" role="status">
            <Check class="size-4" /> Transaksi siap disimpan.
        </div>

        <Card label="Kuota harian" :amount="quota">
            <p class="mt-1 text-xs text-slate-500">Sisa kuota transaksi {{ isIncome ? 'pemasukan' : 'pengeluaran' }} hari ini</p>
        </Card>

        <form class="space-y-4 pb-8 pt-5" @submit.prevent="submit" @input="resetNotice">
            <div class="flex rounded-xl bg-slate-100 p-1" role="tablist" aria-label="Jenis transaksi">
                <button
                    type="button"
                    role="tab"
                    :aria-selected="isIncome"
                    :class="isIncome ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                    class="flex flex-1 items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-xs font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    @click="type = 'income'"
                >
                    <ArrowUpRight class="size-4" /> Pemasukan
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="!isIncome"
                    :class="!isIncome ? 'bg-white text-rose-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                    class="flex flex-1 items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-xs font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    @click="type = 'expense'"
                >
                    <ArrowDownLeft class="size-4" /> Pengeluaran
                </button>
            </div>

            <div>
                <label for="amount" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Nominal</label>
                <div class="flex items-center rounded-xl border border-slate-200 bg-white px-3 focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
                    <span class="text-sm font-semibold text-slate-400">Rp</span>
                    <input id="amount" v-model="form.amount" type="number" min="1" step="1" required inputmode="numeric" placeholder="0" class="w-full border-0 bg-transparent px-2 py-3 text-sm font-bold tabular-nums text-slate-800 placeholder:text-slate-300 focus:ring-0" />
                </div>
            </div>

            <div>
                <label for="category" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Kategori</label>
                <select id="category" v-model="form.category" required class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-indigo-500 focus:ring-indigo-500">
                    <option v-if="isIncome">Jasa Cleaning Rumah</option>
                    <option v-if="isIncome">Penjualan produk</option>
                    <option v-if="!isIncome">Perlengkapan</option>
                    <option v-if="!isIncome">Operasional</option>
                    <option v-if="!isIncome">Transportasi</option>
                </select>
            </div>

            <div>
                <label for="payment-method" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Metode pembayaran</label>
                <select id="payment-method" v-model="form.paymentMethod" required class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-indigo-500 focus:ring-indigo-500">
                    <option>Transfer Bank</option>
                    <option>Cash</option>
                    <option>E-Wallet</option>
                </select>
            </div>

            <div>
                <label for="date" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Tanggal</label>
                <input id="date" v-model="form.date" type="date" required class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-indigo-500 focus:ring-indigo-500" />
            </div>

            <template v-if="isIncome">
                <div>
                    <label for="customer" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Customer</label>
                    <select id="customer" v-model="form.customer" required class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-indigo-500 focus:ring-indigo-500">
                        <option disabled value="">Pilih customer</option>
                        <option>Toko Bintang</option>
                        <option>Pak Wayan</option>
                        <option>Villa Kertalangu</option>
                    </select>
                </div>
                <div>
                    <label for="invoice" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Invoice <span class="font-medium normal-case tracking-normal text-slate-400">(opsional)</span></label>
                    <select id="invoice" v-model="form.invoice" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tanpa invoice</option>
                        <option>INV-0042 · Sisa Rp650.000</option>
                        <option>INV-0040 · Sisa Rp1.800.000</option>
                    </select>
                </div>
            </template>

            <div>
                <label for="note" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Catatan <span class="font-medium normal-case tracking-normal text-slate-400">(opsional)</span></label>
                <textarea id="note" v-model="form.note" rows="3" placeholder="Tambahkan catatan" class="block w-full resize-none rounded-xl border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 placeholder:text-slate-300 focus:border-indigo-500 focus:ring-indigo-500" />
            </div>

            <button type="submit" class="flex min-h-12 w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-sm shadow-indigo-200 transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">
                Simpan transaksi
            </button>
        </form>
    </PrototypeLayout>
</template>
