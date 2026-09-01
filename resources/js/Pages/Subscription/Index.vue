<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CheckCircle2, ChevronLeft, Clock, UploadCloud } from '@lucide/vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';
import Card from '@/Components/ui/Card.vue';

const props = defineProps({
    paid: { type: Boolean, default: false },
    paidUntil: { type: String, default: null },
    pendingPayment: { type: String, default: null },
});

const plan = {
    price: 'Rp99.000',
    bank: 'BCA 123-456-7890',
    accountName: 'PT Sparta Digital',
};

const form = useForm({ proof: null });

// pending → waiting screen; otherwise a plan → payment → upload flow.
const steps = ['plan', 'payment', 'upload'];
const step = ref('plan');
const proofName = ref('');

const title = computed(() => ({
    plan: 'Langganan',
    payment: 'Upgrade ke Paid',
    upload: 'Upload bukti bayar',
}[step.value]));

const transferInfo = computed(() => [
    ['Bank', plan.bank],
    ['a.n.', plan.accountName],
    ['Nominal', plan.price],
]);

const goBack = () => {
    const index = steps.indexOf(step.value);
    if (index > 0) {
        step.value = steps[index - 1];
    }
};

const onProofChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.proof = file;
    proofName.value = file?.name ?? '';
};

const submit = () => {
    form.post(route('subscription.payment.store'), {
        forceFormData: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head :title="pendingPayment ? 'Langganan' : title" />

    <PrototypeLayout>
        <section class="flex items-center gap-3 pb-5 pt-4">
            <Link
                v-if="pendingPayment || step === 'plan'"
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
            <h1 class="text-xl font-bold tracking-tight">{{ pendingPayment ? 'Langganan' : title }}</h1>
        </section>

        <!-- Pending verification -->
        <template v-if="pendingPayment">
            <div class="flex flex-col items-center gap-2 rounded-2xl border border-slate-200 bg-white px-6 py-10 text-center">
                <Clock class="size-9 text-slate-300" />
                <strong class="text-sm font-bold text-slate-800">Menunggu verifikasi</strong>
                <p class="text-xs text-slate-500">Bukti bayar sedang direview admin. Biasanya diproses dalam 1×24 jam.</p>
            </div>
            <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-3.5 text-sm text-amber-800" role="status">
                Selama menunggu, akun tetap berada di paket Free.
            </div>
            <p class="mt-3 pb-8 text-[10px] font-medium uppercase tracking-wider text-slate-400">
                Bukti terkirim {{ pendingPayment }}
            </p>
        </template>

        <template v-else>
            <template v-if="step === 'plan'">
                <Card
                    :label="paid ? 'Paket saat ini' : 'Paket saat ini'"
                    :amount="paid ? 'Paid' : 'Free'"
                    :note="paid ? `Aktif sampai ${paidUntil}` : '0 Employee ber-akun · limit 150 transaksi/hari'"
                />

                <div v-if="paid" class="mt-3 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 p-3.5 text-sm font-semibold text-emerald-700" role="status">
                    <CheckCircle2 class="size-4 shrink-0" /> Langganan aktif — perpanjang kapan saja sebelum jatuh tempo.
                </div>

                <div class="pb-8 pt-5">
                    <button
                        type="button"
                        class="flex min-h-12 w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-sm shadow-indigo-200 transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                        @click="step = 'payment'"
                    >
                        {{ paid ? `Perpanjang — ${plan.price}/bulan` : `Upgrade ke Paid — ${plan.price}/bulan` }}
                    </button>
                    <p class="mt-3 text-xs text-slate-400">
                        Paid: Employee tanpa batas, transaksi tanpa limit. Pay-before-use dengan transfer manual.
                    </p>
                </div>
            </template>

            <template v-else-if="step === 'payment'">
                <Card label="Harga" :amount="plan.price">
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

            <template v-else>
                <form @submit.prevent="submit">
                    <label
                        for="proof"
                        class="flex min-h-[140px] w-full cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-slate-200 bg-white p-6 text-center text-sm text-slate-500 transition hover:border-indigo-300 hover:bg-indigo-50/40 focus-within:ring-2 focus-within:ring-indigo-500"
                    >
                        <UploadCloud class="size-6 text-slate-400" />
                        {{ proofName || 'Tap untuk upload foto bukti transfer (JPG/PNG/WEBP, maks 1 MB)' }}
                    </label>
                    <input id="proof" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="onProofChange" />
                    <p v-if="form.errors.proof" class="mt-1.5 text-xs font-semibold text-rose-600">{{ form.errors.proof }}</p>

                    <div class="mt-4">
                        <span class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Nominal ditransfer</span>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-bold tabular-nums text-slate-700">{{ plan.price }}</div>
                    </div>

                    <div class="pb-8 pt-5">
                        <button
                            type="submit"
                            :disabled="form.processing || !form.proof"
                            class="flex min-h-12 w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-sm shadow-indigo-200 transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Mengirim…' : 'Kirim untuk diverifikasi' }}
                        </button>
                    </div>
                </form>
            </template>
        </template>
    </PrototypeLayout>
</template>
