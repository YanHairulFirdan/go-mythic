<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, Pencil, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';
import Card from '@/Components/ui/Card.vue';

const props = defineProps({
    invoice: {
        type: Object,
        required: true,
    },
});

const formatRupiah = (value) => `Rp${Number(value || 0).toLocaleString('id-ID')}`;

const confirmingDelete = ref(false);
const deleting = ref(false);

const destroy = () => {
    deleting.value = true;
    router.delete(route('invoices.destroy', props.invoice.id), {
        onFinish: () => {
            deleting.value = false;
            confirmingDelete.value = false;
        },
    });
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
            <h1 class="min-w-0 truncate text-xl font-bold tracking-tight">Invoice #{{ props.invoice.id }}</h1>
        </section>

        <div v-if="!props.invoice.is_frozen" class="flex gap-3">
            <Link :href="route('invoices.edit', props.invoice.id)" class="flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                <Pencil class="size-4" /> Edit
            </Link>
            <button type="button" @click="confirmingDelete = true" class="flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl border border-rose-200 bg-white px-4 py-3 text-sm font-semibold text-rose-600 transition hover:bg-rose-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500">
                <Trash2 class="size-4" /> Hapus
            </button>
        </div>
        <p v-else class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold text-slate-500">
            Invoice terkunci karena sudah punya transaksi terkait.
        </p>

        <Card label="Customer" :amount="props.invoice.customer?.name || '—'">
            <p class="mt-1 text-xs text-slate-500">
                Penanggung jawab: {{ props.invoice.employee?.name || 'Tidak ada' }}
            </p>
        </Card>

        <section class="mt-4" aria-labelledby="items-title">
            <h2 id="items-title" class="mb-2 text-sm font-bold">Rincian item</h2>
            <div class="rounded-2xl border border-slate-200 bg-white px-4">
                <div v-for="item in props.invoice.items" :key="item.id" class="flex items-center justify-between gap-4 border-b border-slate-100 py-3.5 text-xs last:border-b-0">
                    <span class="text-slate-600">{{ item.description }}</span>
                    <strong class="font-semibold tabular-nums text-slate-800">{{ formatRupiah(item.amount) }}</strong>
                </div>
            </div>
        </section>

        <Card label="Total invoice" :amount="formatRupiah(props.invoice.nominal_total)" class="mt-4" />

        <div
            v-if="confirmingDelete"
            class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center"
            role="presentation"
            @click.self="confirmingDelete = false"
        >
            <section class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="delete-invoice-title">
                <h2 id="delete-invoice-title" class="text-lg font-bold text-slate-800">Hapus invoice?</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Invoice #{{ props.invoice.id }} beserta seluruh rincian itemnya akan dihapus. Tindakan ini tidak bisa dibatalkan.
                </p>
                <div class="mt-4 flex gap-3">
                    <button
                        type="button"
                        class="flex min-h-11 flex-1 items-center justify-center rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        @click="confirmingDelete = false"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        :disabled="deleting"
                        class="flex min-h-11 flex-1 items-center justify-center rounded-xl bg-rose-600 px-4 py-3 text-sm font-bold text-white hover:bg-rose-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 disabled:opacity-50"
                        @click="destroy"
                    >
                        Hapus
                    </button>
                </div>
            </section>
        </div>
    </PrototypeLayout>
</template>
