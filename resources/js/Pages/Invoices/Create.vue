<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Check, ChevronLeft, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';
import Card from '@/Components/ui/Card.vue';

const saved = ref(false);
const customer = ref('');
const assignee = ref('Made Wirawan');
const items = ref([
    { name: 'Cleaning ruang tamu & dapur', amount: 900000 },
    { name: 'Cleaning 2 kamar mandi', amount: 600000 },
]);

const total = computed(() => items.value.reduce((sum, item) => sum + (Number(item.amount) || 0), 0));
const formattedTotal = computed(() => `Rp${total.value.toLocaleString('id-ID')}`);

const addItem = () => {
    items.value.push({ name: '', amount: 0 });
    saved.value = false;
};

const removeItem = (index) => {
    if (items.value.length > 1) items.value.splice(index, 1);
    saved.value = false;
};

const submit = () => {
    saved.value = true;
};
</script>

<template>
    <Head title="Buat invoice" />

    <PrototypeLayout>
        <section class="flex items-center gap-3 pb-5 pt-4">
            <Link
                :href="route('invoices.index')"
                aria-label="Kembali ke invoice"
                class="flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:border-indigo-200 hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <ChevronLeft class="size-5" />
            </Link>
            <h1 class="text-xl font-bold tracking-tight">Buat invoice</h1>
        </section>

        <div v-if="saved" class="mb-4 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 p-3.5 text-sm font-semibold text-emerald-700" role="status">
            <Check class="size-4" /> Invoice siap disimpan.
        </div>

        <form class="space-y-5 pb-8" @submit.prevent="submit" @input="saved = false">
            <div>
                <label for="customer" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Customer</label>
                <select id="customer" v-model="customer" required class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-indigo-500 focus:ring-indigo-500">
                    <option disabled value="">Pilih customer</option>
                    <option>Toko Bintang</option>
                    <option>Pak Wayan</option>
                    <option>Villa Kertalangu</option>
                </select>
            </div>

            <div>
                <label for="assignee" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Penanggung jawab</label>
                <select id="assignee" v-model="assignee" required class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-indigo-500 focus:ring-indigo-500">
                    <option>Made Wirawan</option>
                    <option>Budi Santoso</option>
                </select>
            </div>

            <section aria-labelledby="items-title">
                <div class="mb-3 flex items-center justify-between">
                    <h2 id="items-title" class="text-sm font-bold">Rincian item</h2>
                    <button type="button" class="flex items-center gap-1 text-xs font-bold text-indigo-600 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500" @click="addItem">
                        <Plus class="size-3.5" /> Tambah item
                    </button>
                </div>

                <div class="space-y-3">
                    <div v-for="(item, index) in items" :key="index" class="rounded-2xl border border-slate-200 bg-white p-3">
                        <div class="flex items-start gap-2">
                            <div class="min-w-0 flex-1">
                                <label :for="`item-name-${index}`" class="sr-only">Nama item {{ index + 1 }}</label>
                                <input :id="`item-name-${index}`" v-model="item.name" type="text" required placeholder="Nama layanan / produk" class="block w-full rounded-xl border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700 placeholder:text-slate-300 focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <button v-if="items.length > 1" type="button" :aria-label="`Hapus item ${index + 1}`" class="flex size-10 shrink-0 items-center justify-center rounded-xl text-slate-400 hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500" @click="removeItem(index)">
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                        <div class="mt-2 flex items-center rounded-xl border border-slate-200 px-3 focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
                            <span class="text-sm font-semibold text-slate-400">Rp</span>
                            <label :for="`item-amount-${index}`" class="sr-only">Nominal item {{ index + 1 }}</label>
                            <input :id="`item-amount-${index}`" v-model.number="item.amount" type="number" min="1" required placeholder="0" class="w-full border-0 px-2 py-2.5 text-sm font-bold tabular-nums text-slate-700 placeholder:text-slate-300 focus:ring-0" />
                        </div>
                    </div>
                </div>
            </section>

            <Card label="Total invoice" :amount="formattedTotal" />

            <button type="submit" class="flex min-h-12 w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-sm shadow-indigo-200 transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">
                Simpan invoice
            </button>
        </form>
    </PrototypeLayout>
</template>
