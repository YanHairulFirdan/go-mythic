<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, Plus, Users } from '@lucide/vue';
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

        <section class="pb-24" aria-label="Daftar customer">
            <div class="divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white px-3">
                <Link
                    v-for="customer in customers"
                    :key="customer.id"
                    :href="route('customers.show', customer.id)"
                    class="flex items-center gap-3 py-3.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500"
                >
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600">
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

        <Link
            :href="route('customers.create')"
            aria-label="Tambah customer"
            class="fixed bottom-24 right-5 z-20 flex size-14 items-center justify-center rounded-full bg-primary-600 text-white shadow-lg shadow-primary-200 transition hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 sm:absolute sm:bottom-20 sm:right-5"
        >
            <Plus class="size-6" />
        </Link>
    </PrototypeLayout>
</template>
