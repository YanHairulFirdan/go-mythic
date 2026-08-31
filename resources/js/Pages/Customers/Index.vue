<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, Users } from '@lucide/vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineProps({
    customers: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Customer" />

    <PrototypeLayout>
        <section class="pb-5 pt-4">
            <PageHeader title="Customer" />
        </section>

        <Link
            :href="route('customers.create')"
            class="flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
        >
            + Tambah customer
        </Link>

        <section class="mt-4 pb-4" aria-label="Daftar customer">
            <div class="divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white px-3">
                <Link
                    v-for="customer in customers"
                    :key="customer.id"
                    :href="route('customers.show', customer.id)"
                    class="flex items-center gap-3 py-3.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500"
                >
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                        <Users class="size-[18px]" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <strong class="block truncate text-xs font-bold text-slate-800">{{ customer.name }}</strong>
                        <small class="mt-1 block truncate text-[10px] text-slate-400">{{ customer.contact || 'Tanpa kontak' }}</small>
                    </span>
                    <ChevronRight class="size-4 shrink-0 text-slate-300" />
                </Link>
                <p v-if="customers.length === 0" class="py-10 text-center text-sm text-slate-400">
                    Belum ada customer.
                </p>
            </div>
        </section>
    </PrototypeLayout>
</template>
