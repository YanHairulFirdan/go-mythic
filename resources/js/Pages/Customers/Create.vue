<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Check, ChevronLeft } from '@lucide/vue';
import { ref } from 'vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';

const saved = ref(false);
const form = ref({
    name: '',
    contact: '',
    address: '',
    note: '',
});

const submit = () => {
    saved.value = true;
};
</script>

<template>
    <Head title="Tambah customer" />

    <PrototypeLayout>
        <section class="flex items-center gap-3 pb-5 pt-4">
            <Link
                :href="route('customers.index')"
                aria-label="Kembali ke customer"
                class="flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:border-indigo-200 hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <ChevronLeft class="size-5" />
            </Link>
            <h1 class="text-xl font-bold tracking-tight">Tambah customer</h1>
        </section>

        <div v-if="saved" class="mb-4 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 p-3.5 text-sm font-semibold text-emerald-700" role="status">
            <Check class="size-4" /> Customer siap disimpan.
        </div>

        <form class="space-y-4 pb-8" @submit.prevent="submit" @input="saved = false">
            <div>
                <label for="name" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Nama</label>
                <input id="name" v-model="form.name" type="text" required placeholder="Nama customer" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 placeholder:text-slate-300 focus:border-indigo-500 focus:ring-indigo-500" />
            </div>

            <div>
                <label for="contact" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Kontak</label>
                <input id="contact" v-model="form.contact" type="text" placeholder="0812-3456-7890" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 placeholder:text-slate-300 focus:border-indigo-500 focus:ring-indigo-500" />
            </div>

            <div>
                <label for="address" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Alamat</label>
                <input id="address" v-model="form.address" type="text" placeholder="Jl. contoh No.1, Denpasar" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 placeholder:text-slate-300 focus:border-indigo-500 focus:ring-indigo-500" />
            </div>

            <div>
                <label for="note" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Catatan <span class="font-medium normal-case tracking-normal text-slate-400">(opsional)</span></label>
                <textarea id="note" v-model="form.note" rows="3" placeholder="Tambahkan catatan" class="block w-full resize-none rounded-xl border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 placeholder:text-slate-300 focus:border-indigo-500 focus:ring-indigo-500" />
            </div>

            <button type="submit" class="flex min-h-12 w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-sm shadow-indigo-200 transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">
                Simpan customer
            </button>
        </form>
    </PrototypeLayout>
</template>
