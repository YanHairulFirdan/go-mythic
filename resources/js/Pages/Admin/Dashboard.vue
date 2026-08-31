<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    stats: {
        type: Object,
        required: true,
    },
});

const cards = [
    { key: 'companies', label: 'Total company', route: 'admin.companies.index' },
    { key: 'paid', label: 'Company Paid', route: 'admin.companies.index', query: { status: 'paid' } },
    { key: 'free', label: 'Company Free', route: 'admin.companies.index', query: { status: 'free' } },
    { key: 'pendingPayments', label: 'Pembayaran pending', route: 'admin.payments.index', query: { status: 'pending' } },
];
</script>

<template>
    <Head title="Dashboard Admin" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Link
                        v-for="card in cards"
                        :key="card.key"
                        :href="route(card.route, card.query ?? {})"
                        class="block rounded-lg bg-white p-6 shadow-sm transition hover:shadow"
                    >
                        <div class="text-sm font-medium text-gray-500">{{ card.label }}</div>
                        <div class="mt-2 text-3xl font-semibold text-gray-900">{{ stats[card.key] }}</div>
                    </Link>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
