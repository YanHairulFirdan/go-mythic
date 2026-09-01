<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, Pencil, Trash2 } from '@lucide/vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';
import Card from '@/Components/ui/Card.vue';

const props = defineProps({
    customer: {
        type: Object,
        required: true,
    },
});

const destroy = () => {
    if (window.confirm('Hapus customer ini?')) {
        router.delete(route('customers.destroy', props.customer.id));
    }
};
</script>

<template>
    <Head title="Detail customer" />

    <PrototypeLayout>
        <section class="flex items-center gap-3 pb-5 pt-4">
            <Link
                :href="route('customers.index')"
                aria-label="Kembali ke customer"
                class="flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:border-indigo-200 hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <ChevronLeft class="size-5" />
            </Link>
            <h1 class="min-w-0 truncate text-xl font-bold tracking-tight">{{ props.customer.name }}</h1>
        </section>

        <div class="flex gap-3">
            <Link :href="route('customers.edit', props.customer.id)" class="flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                <Pencil class="size-4" /> Edit
            </Link>
            <button type="button" @click="destroy" class="flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl border border-rose-200 bg-white px-4 py-3 text-sm font-semibold text-rose-600 transition hover:bg-rose-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500">
                <Trash2 class="size-4" /> Hapus
            </button>
        </div>

        <Card label="Kontak" :amount="props.customer.contact || '—'">
            <p class="mt-1 text-xs text-slate-500">{{ props.customer.address || 'Alamat belum diisi' }}</p>
        </Card>
    </PrototypeLayout>
</template>
