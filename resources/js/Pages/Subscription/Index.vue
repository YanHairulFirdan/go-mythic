<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, Clock, UploadCloud } from '@lucide/vue';
import { computed, ref } from 'vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';
import Card from '@/Components/ui/Card.vue';

const props = defineProps({
    paid: { type: Boolean, default: false },
    subscriptionWarning: { type: String, default: null },
    plan: {
        type: Object,
        default: () => ({
            current: 'Free',
            detail: '0 Employee ber-akun · limit 150 transaksi/hari',
            price: 'Rp99.000',
            bank: 'BCA 123-456-7890',
            accountName: 'PT Sparta Digital',
            amount: 'Rp99.000',
        }),
    },
});

const steps = ['plan', 'payment', 'upload', 'pending'];
const step = ref('plan');
const proofChosen = ref(false);
const form = useForm({ proof: null });

const chooseProof = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.proof = file;
    proofChosen.value = file !== null;
};

const submitProof = () => {
    if (! form.proof) {
        return;
    }

    form.post(route('subscription.payment.store'), {
        forceFormData: true,
        onSuccess: () => {
            step.value = 'pending';
        },
    });
};

const title = computed(() => ({
    plan: 'Langganan',
    payment: 'Upgrade ke Paid',
    upload: 'Upload bukti bayar',
    pending: 'Langganan',
}[step.value]));

const transferInfo = computed(() => [
    ['Bank', props.plan.bank],
    ['a.n.', props.plan.accountName],
    ['Nominal', props.plan.amount],
]);

const goBack = () => {
    const index = steps.indexOf(step.value);
    if (index > 0) {
        step.value = steps[index - 1];
    }
};
</script>

<template>
    <Head :title="title" />

    <PrototypeLayout>
        <section class="flex items-center gap-3 pb-5 pt-4">
            <Link
                v-if="step === 'plan'"
                :href="route('dashboard')"
                aria-label="Kembali ke beranda"
                class="flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:border-indigo-200 hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <ChevronLeft class="size-5" />
            </Link>
            <button
                v-else
                type="button"
                aria-label="Kembali"
                class="flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:border-indigo-200 hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                @click="goBack"
            >
                <ChevronLeft class="size-5" />
            </button>
            <h1 class="text-xl font-bold tracking-tight">{{ title }}</h1>
        </section>

        <template v-if="step === 'plan'">
            <Card
                label="Paket saat ini"
                :amount="props.paid ? 'Paid' : 'Free'"
                :note="props.paid
                    ? 'Employee tanpa batas · transaksi tanpa limit'
                    : '0 Employee ber-akun · limit 150 transaksi/hari'"
            />

            <div
                v-if="props.subscriptionWarning"
                class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-3.5 text-sm text-amber-800"
                role="alert"
            >
                {{ props.subscriptionWarning }}
            </div>

            <div class="pb-8 pt-5">
                <button
                    type="button"
                    class="flex min-h-12 w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-sm shadow-indigo-200 transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                    @click="step = 'payment'"
                >
                    Upgrade ke Paid — {{ props.plan.price }}/bulan
                </button>
                <p class="mt-3 text-xs text-slate-400">
                    Paid: Employee tanpa batas, transaksi tanpa limit. Pay-before-use dengan transfer manual.
                </p>
            </div>
        </template>

        <template v-else-if="step === 'payment'">
            <Card label="Harga" :amount="props.plan.price">
                <p class="mt-1 text-xs font-semibold text-slate-400">per bulan</p>
                <p class="mt-2 text-xs text-slate-500">Employee tanpa batas, transaksi tanpa limit</p>
            </Card>

            <h2 class="mb-2 mt-4 text-sm font-bold">Instruksi transfer</h2>
            <section class="rounded-2xl border border-slate-200 bg-white px-4" aria-label="Instruksi transfer">
                <div v-for="row in transferInfo" :key="row[0]" class="flex items-start justify-between gap-4 border-b border-slate-100 py-3.5 text-xs last:border-b-0">
                    <span class="text-slate-400">{{ row[0] }}</span>
                    <strong class="text-right font-semibold text-slate-700">{{ row[1] }}</strong>
                </div>
            </section>

            <div class="pb-8 pt-5">
                <button
                    type="button"
                    class="flex min-h-12 w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-sm shadow-indigo-200 transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                    @click="step = 'upload'"
                >
                    Sudah transfer, upload bukti
                </button>
            </div>
        </template>

        <template v-else-if="step === 'upload'">
            <label class="flex min-h-[140px] w-full cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-slate-200 bg-white p-6 text-center text-sm text-slate-500 transition hover:border-indigo-300 hover:bg-indigo-50/40 focus-within:outline-none focus-within:ring-2 focus-within:ring-indigo-500">
                <UploadCloud class="size-6 text-slate-400" />
                {{ proofChosen ? form.proof.name : 'Tap untuk upload foto bukti transfer' }}
                <input
                    type="file"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    class="sr-only"
                    @change="chooseProof"
                />
            </label>
            <p v-if="form.errors.proof" class="mt-2 text-xs text-red-600" role="alert">
                {{ form.errors.proof }}
            </p>

            <div class="mt-4">
                <span class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Nominal ditransfer</span>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-bold tabular-nums text-slate-700">{{ props.plan.amount }}</div>
            </div>

            <div class="pb-8 pt-5">
                <button
                    type="button"
                    :disabled="!form.proof || form.processing"
                    class="flex min-h-12 w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-sm shadow-indigo-200 transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="submitProof"
                >
                    {{ form.processing ? 'Mengirim...' : 'Kirim untuk diverifikasi' }}
                </button>
            </div>
        </template>

        <template v-else>
            <div class="flex flex-col items-center gap-2 rounded-2xl border border-slate-200 bg-white px-6 py-10 text-center">
                <Clock class="size-9 text-slate-300" />
                <strong class="text-sm font-bold text-slate-800">Menunggu verifikasi</strong>
                <p class="text-xs text-slate-500">Bukti bayar sedang direview admin. Biasanya diproses dalam 1×24 jam.</p>
            </div>

            <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-3.5 text-sm text-amber-800" role="status">
                Selama menunggu, akun tetap berada di paket Free.
            </div>

            <p class="mt-3 pb-8 text-[10px] font-medium uppercase tracking-wider text-slate-400">
                Bukti-transfer.jpg · Terkirim 14 Agustus 2026
            </p>
        </template>
    </PrototypeLayout>
</template>
