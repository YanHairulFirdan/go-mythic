<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    paid: {
        type: Boolean,
    },
    paidUntil: {
        type: String,
        default: null,
    },
    pendingPayment: {
        type: String,
        default: null,
    },
});

const form = useForm({
    proof: null,
});

const submit = () => {
    form.post(route('subscription.payment.store'), {
        forceFormData: true,
        onSuccess: () => form.reset('proof'),
    });
};
</script>

<template>
    <Head title="Langganan" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Langganan
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
                <!-- Paket saat ini -->
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900">
                        Paket saat ini
                    </h3>
                    <p class="mt-1 text-sm text-gray-600">
                        <span v-if="paid">
                            Paid — Employee tanpa batas, transaksi tanpa
                            limit.
                            <span v-if="paidUntil">
                                Aktif sampai {{ paidUntil }}.</span
                            >
                        </span>
                        <span v-else>
                            Free — 0 Employee ber-akun, limit 150
                            transaksi/hari.
                        </span>
                    </p>
                </div>

                <!-- Upgrade ke Paid -->
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900">
                        Upgrade ke Paid
                    </h3>
                    <p class="mt-2 text-2xl font-semibold text-indigo-600">
                        Rp99.000/bulan
                    </p>
                    <p class="mt-1 text-sm text-gray-600">
                        Employee tanpa batas, transaksi tanpa limit.
                    </p>

                    <div class="mt-4 rounded-md bg-gray-50 p-4 text-sm text-gray-700">
                        <p class="font-medium text-gray-900">
                            Instruksi transfer
                        </p>
                        <p>Bank: BCA 123-456-7890</p>
                        <p>a.n.: PT Sparta Digital</p>
                        <p>Nominal: Rp99.000</p>
                    </div>
                </div>

                <!-- Upload bukti bayar -->
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900">
                        Upload bukti bayar
                    </h3>
                    <p
                        v-if="pendingPayment"
                        class="mt-2 rounded-md bg-amber-50 p-3 text-sm text-amber-700"
                    >
                        Pengajuan pada {{ pendingPayment }} masih menunggu
                        verifikasi admin. Anda tetap di Free sampai
                        disetujui.
                    </p>

                    <form
                        class="mt-4"
                        @submit.prevent="submit"
                    >
                        <InputLabel
                            for="proof"
                            value="Bukti transfer (JPG/PNG/WebP, maks 1 MB)"
                        />
                        <input
                            id="proof"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm text-gray-600 file:me-4 file:rounded-md file:border-0 file:bg-indigo-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-indigo-700"
                            @input="form.proof = $event.target.files[0]"
                            required
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.proof"
                        />

                        <div class="mt-4 flex items-center justify-end">
                            <PrimaryButton
                                :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing"
                            >
                                Kirim untuk diverifikasi
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
