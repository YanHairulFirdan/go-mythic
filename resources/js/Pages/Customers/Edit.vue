<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';

const props = defineProps({
    customer: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: props.customer.name ?? '',
    contact: props.customer.contact ?? '',
    address: props.customer.address ?? '',
});

const submit = () => {
    form.patch(route('customers.update', props.customer.id));
};
</script>

<template>
    <Head title="Edit customer" />

    <PrototypeLayout>
        <section class="flex items-center gap-3 pb-5 pt-4">
            <Link
                :href="route('customers.show', props.customer.id)"
                aria-label="Kembali ke detail customer"
                class="flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:border-indigo-200 hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <ChevronLeft class="size-5" />
            </Link>
            <h1 class="text-xl font-bold tracking-tight">Edit customer</h1>
        </section>

        <form class="space-y-4 pb-8" @submit.prevent="submit">
            <div>
                <label for="name" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Nama</label>
                <input id="name" v-model="form.name" type="text" required placeholder="Nama customer" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 placeholder:text-slate-300 focus:border-indigo-500 focus:ring-indigo-500" />
                <p v-if="form.errors.name" class="mt-1.5 text-xs font-semibold text-rose-600">{{ form.errors.name }}</p>
            </div>

            <div>
                <label for="contact" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Kontak <span class="font-medium normal-case tracking-normal text-slate-400">(opsional)</span></label>
                <input id="contact" v-model="form.contact" type="text" placeholder="0812-3456-7890" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 placeholder:text-slate-300 focus:border-indigo-500 focus:ring-indigo-500" />
                <p v-if="form.errors.contact" class="mt-1.5 text-xs font-semibold text-rose-600">{{ form.errors.contact }}</p>
            </div>

            <div>
                <label for="address" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Alamat <span class="font-medium normal-case tracking-normal text-slate-400">(opsional)</span></label>
                <textarea id="address" v-model="form.address" rows="3" placeholder="Jl. contoh No.1, Denpasar" class="block w-full resize-none rounded-xl border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 placeholder:text-slate-300 focus:border-indigo-500 focus:ring-indigo-500" />
                <p v-if="form.errors.address" class="mt-1.5 text-xs font-semibold text-rose-600">{{ form.errors.address }}</p>
            </div>

            <button type="submit" :disabled="form.processing" class="flex min-h-12 w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-sm shadow-indigo-200 transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:opacity-50">
                Simpan perubahan
            </button>
        </form>
    </PrototypeLayout>
</template>
