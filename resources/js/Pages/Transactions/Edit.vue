<script setup lang="ts">
import { computed, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight, ChevronLeft, Paperclip, TriangleAlert } from '@lucide/vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';

type TransactionType = 'income' | 'expense';

interface Category {
    id: number;
    name: string;
    type: TransactionType;
}

interface CapitalPeriod {
    start_date: string;
    end_date: string;
}

interface PageProps {
    auth: { user: { role?: string } | null };
    [key: string]: unknown;
}

interface InvoiceOption {
    id: number;
    customer: string | null;
    nominal_total: number;
    remaining: number;
}

interface NamedOption {
    id: number;
    name: string;
}

interface TransactionForm {
    id: number;
    type: TransactionType;
    amount: number;
    category_id: number;
    invoice_id: number | null;
    customer_id: number | null;
    employee_id: number | null;
    transaction_date: string;
    payment_method: string;
    notes: string | null;
    attachment_url: string | null;
}

interface Props {
    transaction: TransactionForm;
    categories: Category[];
    capitalPeriods: CapitalPeriod[];
    invoices: InvoiceOption[];
    customers: NamedOption[];
    employees: NamedOption[];
}

const props = defineProps<Props>();

const page = usePage<PageProps>();
const isOwner = computed((): boolean => page.props.auth.user?.role === 'owner');
const capitalHref = route('capital.index');

const paymentMethods: Array<{ value: string; label: string }> = [
    { value: 'cash', label: 'Tunai' },
    { value: 'transfer', label: 'Transfer bank' },
    { value: 'qris', label: 'QRIS' },
    { value: 'other', label: 'Lainnya' },
];

const today = new Date().toISOString().slice(0, 10);
const backHref = route('transactions.show', props.transaction.id);

const form = useForm<{
    type: TransactionType;
    amount: string;
    category_id: number | '';
    invoice_id: number | '';
    customer_id: number | '';
    employee_id: number | '';
    transaction_date: string;
    payment_method: string;
    notes: string;
    attachment: File | null;
}>({
    type: props.transaction.type,
    amount: String(props.transaction.amount),
    category_id: props.transaction.category_id,
    invoice_id: props.transaction.invoice_id ?? '',
    customer_id: props.transaction.customer_id ?? '',
    employee_id: props.transaction.employee_id ?? '',
    transaction_date: props.transaction.transaction_date,
    payment_method: props.transaction.payment_method,
    notes: props.transaction.notes ?? '',
    attachment: null,
});

const isIncome = computed((): boolean => form.type === 'income');

const rupiah = (value: number): string => `Rp${Number(value).toLocaleString('id-ID')}`;
const selectedInvoice = computed((): InvoiceOption | undefined =>
    props.invoices.find((invoice) => invoice.id === form.invoice_id));
const customerLockedByInvoice = computed((): boolean => form.invoice_id !== '');

// US-MK-04/05: submit is blocked unless the chosen date falls inside a capital period.
const dateHasCapital = computed((): boolean =>
    props.capitalPeriods.some((period) =>
        form.transaction_date >= period.start_date && form.transaction_date <= period.end_date));

const availableCategories = computed((): Category[] =>
    props.categories.filter((category) => category.type === form.type));

watch(() => form.type, (next, previous) => {
    if (next !== previous) {
        form.category_id = '';
        // US-INV-02 AC4 / US-CUST-02 AC1: invoice + customer links are income-only.
        form.invoice_id = '';
        form.customer_id = '';
    }
});

const onAttachmentChange = (event: Event): void => {
    const input = event.target as HTMLInputElement;
    form.attachment = input.files?.[0] ?? null;
};

const submit = (): void => {
    form
        .transform((data) => ({ ...data, _method: 'put' }))
        .post(route('transactions.update', props.transaction.id), { forceFormData: true });
};
</script>

<template>
    <Head title="Edit transaksi" />

    <PrototypeLayout>
        <section class="flex items-center gap-3 pb-5 pt-4">
            <Link
                :href="backHref"
                aria-label="Kembali ke detail"
                class="flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:border-primary-200 hover:text-primary-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
                <ChevronLeft class="size-5" />
            </Link>
            <h1 class="text-xl font-bold tracking-tight">Edit transaksi</h1>
        </section>

        <form class="space-y-4 pb-8" @submit.prevent="submit">
            <div
                v-if="!dateHasCapital"
                class="flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2.5 text-rose-800"
                role="alert"
            >
                <TriangleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                <div class="min-w-0 flex-1 text-xs">
                    <p class="font-bold">Belum ada modal aktif untuk tanggal ini</p>
                    <p class="mt-0.5 text-[11px] text-rose-700">
                        <template v-if="isOwner">Setiap transaksi butuh modal/kas aktif sebagai baseline.</template>
                        <template v-else>Hubungi Owner untuk mengatur modal/kas usaha.</template>
                    </p>
                    <Link
                        v-if="isOwner"
                        :href="capitalHref"
                        class="mt-1.5 inline-flex items-center rounded-lg bg-rose-600 px-2.5 py-1 text-[11px] font-bold text-white transition hover:bg-rose-700"
                    >
                        Set Modal Sekarang
                    </Link>
                </div>
            </div>

            <div class="flex rounded-xl bg-slate-100 p-1" role="tablist" aria-label="Jenis transaksi">
                <button
                    type="button"
                    role="tab"
                    :aria-selected="isIncome"
                    :class="isIncome ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                    class="flex flex-1 items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-xs font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                    @click="form.type = 'income'"
                >
                    <ArrowUpRight class="size-4" /> Pemasukan
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="!isIncome"
                    :class="!isIncome ? 'bg-white text-rose-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                    class="flex flex-1 items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-xs font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                    @click="form.type = 'expense'"
                >
                    <ArrowDownLeft class="size-4" /> Pengeluaran
                </button>
            </div>

            <div>
                <label for="amount" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Nominal</label>
                <div class="flex items-center rounded-xl border border-slate-200 bg-white px-3 focus-within:border-primary-500 focus-within:ring-1 focus-within:ring-primary-500">
                    <span class="text-sm font-semibold text-slate-400">Rp</span>
                    <input
                        id="amount"
                        v-model="form.amount"
                        type="number"
                        min="1"
                        step="1"
                        inputmode="numeric"
                        class="w-full border-0 bg-transparent px-2 py-3 text-sm font-bold tabular-nums text-slate-800 focus:ring-0"
                    />
                </div>
                <p v-if="form.errors.amount" class="mt-1.5 text-xs font-semibold text-rose-600">{{ form.errors.amount }}</p>
            </div>

            <div>
                <label for="category" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Kategori</label>
                <select
                    id="category"
                    v-model="form.category_id"
                    class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-primary-500 focus:ring-primary-500"
                >
                    <option disabled value="">Pilih kategori</option>
                    <option v-for="category in availableCategories" :key="category.id" :value="category.id">{{ category.name }}</option>
                </select>
                <p v-if="form.errors.category_id" class="mt-1.5 text-xs font-semibold text-rose-600">{{ form.errors.category_id }}</p>
            </div>

            <div v-if="isIncome">
                <label for="invoice" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                    Invoice <span class="font-medium normal-case tracking-normal text-slate-400">(opsional)</span>
                </label>
                <select
                    id="invoice"
                    v-model="form.invoice_id"
                    class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-primary-500 focus:ring-primary-500"
                >
                    <option value="">Tanpa invoice</option>
                    <option v-for="invoice in props.invoices" :key="invoice.id" :value="invoice.id">
                        #{{ invoice.id }} · {{ invoice.customer ?? 'Tanpa customer' }} · sisa {{ rupiah(invoice.remaining) }}
                    </option>
                </select>
                <p v-if="selectedInvoice" class="mt-1 text-[11px] text-slate-500">
                    Customer transaksi otomatis mengikuti invoice.
                </p>
                <p v-if="form.errors.invoice_id" class="mt-1.5 text-xs font-semibold text-rose-600">{{ form.errors.invoice_id }}</p>
            </div>

            <div v-if="isIncome && !customerLockedByInvoice">
                <label for="customer" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                    Customer <span class="font-medium normal-case tracking-normal text-slate-400">(opsional)</span>
                </label>
                <select
                    id="customer"
                    v-model="form.customer_id"
                    class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-primary-500 focus:ring-primary-500"
                >
                    <option value="">Tanpa customer</option>
                    <option v-for="customer in props.customers" :key="customer.id" :value="customer.id">{{ customer.name }}</option>
                </select>
                <p v-if="form.errors.customer_id" class="mt-1.5 text-xs font-semibold text-rose-600">{{ form.errors.customer_id }}</p>
            </div>
            <p v-else-if="isIncome && selectedInvoice" class="text-[11px] text-slate-500">
                Customer: <strong class="text-slate-700">{{ selectedInvoice.customer ?? '—' }}</strong> (dari invoice)
            </p>

            <div>
                <label for="employee" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                    Pelaksana <span class="font-medium normal-case tracking-normal text-slate-400">(opsional)</span>
                </label>
                <select
                    id="employee"
                    v-model="form.employee_id"
                    class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-primary-500 focus:ring-primary-500"
                >
                    <option value="">Tidak ada</option>
                    <option v-for="employee in props.employees" :key="employee.id" :value="employee.id">{{ employee.name }}</option>
                </select>
                <p v-if="form.errors.employee_id" class="mt-1.5 text-xs font-semibold text-rose-600">{{ form.errors.employee_id }}</p>
            </div>

            <div>
                <label for="payment-method" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Metode pembayaran</label>
                <select
                    id="payment-method"
                    v-model="form.payment_method"
                    class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-primary-500 focus:ring-primary-500"
                >
                    <option v-for="method in paymentMethods" :key="method.value" :value="method.value">{{ method.label }}</option>
                </select>
                <p v-if="form.errors.payment_method" class="mt-1.5 text-xs font-semibold text-rose-600">{{ form.errors.payment_method }}</p>
            </div>

            <div>
                <label for="date" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Tanggal</label>
                <input
                    id="date"
                    v-model="form.transaction_date"
                    type="date"
                    :max="today"
                    class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-primary-500 focus:ring-primary-500"
                />
                <p v-if="form.errors.transaction_date" class="mt-1.5 text-xs font-semibold text-rose-600">{{ form.errors.transaction_date }}</p>
            </div>

            <div>
                <label for="notes" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                    Catatan <span class="font-medium normal-case tracking-normal text-slate-400">(opsional)</span>
                </label>
                <textarea
                    id="notes"
                    v-model="form.notes"
                    rows="3"
                    class="block w-full resize-none rounded-xl border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 focus:border-primary-500 focus:ring-primary-500"
                />
                <p v-if="form.errors.notes" class="mt-1.5 text-xs font-semibold text-rose-600">{{ form.errors.notes }}</p>
            </div>

            <div>
                <label for="attachment" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                    Lampiran <span class="font-medium normal-case tracking-normal text-slate-400">(opsional · ganti dengan JPG/PNG/WEBP maks 1 MB)</span>
                </label>
                <a
                    v-if="props.transaction.attachment_url"
                    :href="props.transaction.attachment_url"
                    target="_blank"
                    rel="noopener"
                    class="mb-2 inline-flex items-center gap-2 text-xs font-semibold text-primary-600 hover:text-primary-700"
                >
                    <Paperclip class="size-3.5" /> Lampiran saat ini
                </a>
                <input
                    id="attachment"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary-700"
                    @change="onAttachmentChange"
                />
                <p v-if="form.errors.attachment" class="mt-1.5 text-xs font-semibold text-rose-600">{{ form.errors.attachment }}</p>
            </div>

            <button
                type="submit"
                :disabled="form.processing || !dateHasCapital"
                class="flex min-h-12 w-full items-center justify-center rounded-xl bg-primary-600 px-4 py-3 text-sm font-bold text-white shadow-sm shadow-primary-200 transition hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 disabled:opacity-50"
            >
                {{ form.processing ? 'Menyimpan…' : 'Simpan perubahan' }}
            </button>
        </form>
    </PrototypeLayout>
</template>
