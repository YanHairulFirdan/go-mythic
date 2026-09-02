<script setup>
import { ref } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';

defineProps({
    payments: {
        type: Array,
        required: true,
    },
});

const approvingId = ref(null);

const approve = (payment) => {
    if (approvingId.value !== null || !window.confirm('Setujui pembayaran ini?')) {
        return;
    }
    approvingId.value = payment.id;
    router.post(route('admin.payments.approve', payment.id), {}, {
        onFinish: () => { approvingId.value = null; },
    });
};
</script>

<template>
    <Head title="Pembayaran Admin" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Pembayaran</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <section class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    <th class="px-3 py-3">Company</th>
                                    <th class="px-3 py-3">Nominal</th>
                                    <th class="px-3 py-3">Bukti</th>
                                    <th class="px-3 py-3">Status</th>
                                    <th class="px-3 py-3">Diajukan</th>
                                    <th class="px-3 py-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                                <tr v-for="payment in payments" :key="payment.id">
                                    <td class="whitespace-nowrap px-3 py-3 font-medium">{{ payment.company.name }}</td>
                                    <td class="whitespace-nowrap px-3 py-3">Rp{{ Number(payment.amount).toLocaleString('id-ID') }}</td>
                                    <td class="whitespace-nowrap px-3 py-3">{{ payment.attachment_path }}</td>
                                    <td class="whitespace-nowrap px-3 py-3">{{ payment.status }}</td>
                                    <td class="whitespace-nowrap px-3 py-3">{{ payment.created_at }}</td>
                                    <td class="whitespace-nowrap px-3 py-3">
                                        <PrimaryButton
                                            v-if="payment.status === 'pending'"
                                            :disabled="approvingId === payment.id"
                                            @click="approve(payment)"
                                        >
                                            {{ approvingId === payment.id ? 'Menyetujui…' : 'Setujui' }}
                                        </PrimaryButton>
                                        <span v-else class="text-gray-500">Selesai</span>
                                    </td>
                                </tr>
                                <tr v-if="payments.length === 0">
                                    <td colspan="6" class="px-3 py-8 text-center text-gray-500">Belum ada pembayaran.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
