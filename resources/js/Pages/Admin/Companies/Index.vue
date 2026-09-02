<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

interface Company {
    id: number;
    name: string;
    owner_name: string;
    email: string;
    subscription_status: 'Paid' | 'Free';
    paid_until: string | null;
}

interface Filters {
    search: string | null;
    status: string | null;
}

interface Props {
    companies: Company[];
    filters: Filters;
}

const props = defineProps<Props>();

const form = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const applyFilters = (): void => {
    router.get(route('admin.companies.index'), {
        search: form.search || undefined,
        status: form.status || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = (): void => {
    form.search = '';
    form.status = '';
    applyFilters();
};

const formatDate = (value: string | null): string => (value
    ? new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
    : '—');
</script>

<template>
    <Head title="Company Admin" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Company</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <form
                    class="flex flex-col gap-3 rounded-lg bg-white p-4 shadow-sm sm:flex-row sm:items-end"
                    @submit.prevent="applyFilters"
                >
                    <div class="flex-1">
                        <label for="search" class="block text-sm font-medium text-gray-700">Cari</label>
                        <input
                            id="search"
                            v-model="form.search"
                            type="search"
                            placeholder="Nama usaha atau email Owner"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                        />
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select
                            id="status"
                            v-model="form.status"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                        >
                            <option value="">Semua</option>
                            <option value="paid">Paid</option>
                            <option value="free">Free</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button
                            type="submit"
                            class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700"
                        >
                            Terapkan
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-4 py-2 text-sm font-medium text-gray-600 underline"
                            @click="resetFilters"
                        >
                            Reset
                        </button>
                    </div>
                </form>

                <section class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    <th class="px-3 py-3">Nama usaha</th>
                                    <th class="px-3 py-3">Owner</th>
                                    <th class="px-3 py-3">Email Owner</th>
                                    <th class="px-3 py-3">Status</th>
                                    <th class="px-3 py-3">Berlaku sampai</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                                <tr v-for="company in props.companies" :key="company.id">
                                    <td class="whitespace-nowrap px-3 py-3 font-medium">{{ company.name }}</td>
                                    <td class="whitespace-nowrap px-3 py-3">{{ company.owner_name }}</td>
                                    <td class="whitespace-nowrap px-3 py-3">{{ company.email }}</td>
                                    <td class="whitespace-nowrap px-3 py-3">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                            :class="company.subscription_status === 'Paid'
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-gray-100 text-gray-600'"
                                        >
                                            {{ company.subscription_status }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3">{{ formatDate(company.paid_until) }}</td>
                                </tr>
                                <tr v-if="props.companies.length === 0">
                                    <td colspan="5" class="px-3 py-8 text-center text-gray-500">
                                        Tidak ada company yang cocok.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
