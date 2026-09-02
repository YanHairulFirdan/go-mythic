<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';
import Card from '@/Components/ui/Card.vue';

const props = defineProps({
    customers: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
});

const form = useForm({
    customer_id: '',
    employee_id: '',
    items: [{ description: '', amount: null }],
});

const total = computed(() => form.items.reduce((sum, item) => sum + (Number(item.amount) || 0), 0));
const formattedTotal = computed(() => `Rp${total.value.toLocaleString('id-ID')}`);

const addItem = () => form.items.push({ description: '', amount: null });
const removeItem = (index) => {
    if (form.items.length > 1) form.items.splice(index, 1);
};

const submit = () => form.post(route('invoices.store'));
</script>

<template>
    <Head title="Buat invoice" />

    <PrototypeLayout>
        <section class="flex items-center gap-3 pb-5 pt-4">
            <Link
                :href="route('invoices.index')"
                aria-label="Kembali ke invoice"
                class="flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:border-primary-200 hover:text-primary-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
                <ChevronLeft class="size-5" />
            </Link>
            <h1 class="text-xl font-bold tracking-tight">Buat invoice</h1>
        </section>

        <form class="space-y-5 pb-8" @submit.prevent="submit">
            <div>
                <div class="mb-1.5 flex items-center justify-between">
                    <label for="customer_id" class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Customer</label>
                    <Link :href="route('customers.create')" class="text-xs font-bold text-primary-600 hover:text-primary-700">+ Customer baru</Link>
                </div>
                <select id="customer_id" v-model="form.customer_id" required class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                    <option disabled value="">Pilih customer</option>
                    <option v-for="customer in props.customers" :key="customer.id" :value="customer.id">{{ customer.name }}</option>
                </select>
                <p v-if="form.errors.customer_id" class="mt-1.5 text-xs font-semibold text-rose-600">{{ form.errors.customer_id }}</p>
            </div>

            <div>
                <label for="employee_id" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Penanggung jawab <span class="font-medium normal-case tracking-normal text-slate-400">(opsional)</span></label>
                <select id="employee_id" v-model="form.employee_id" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                    <option value="">Tanpa penanggung jawab</option>
                    <option v-for="employee in props.employees" :key="employee.id" :value="employee.id">{{ employee.name }}</option>
                </select>
                <p v-if="form.errors.employee_id" class="mt-1.5 text-xs font-semibold text-rose-600">{{ form.errors.employee_id }}</p>
            </div>

            <section aria-labelledby="items-title">
                <div class="mb-3 flex items-center justify-between">
                    <h2 id="items-title" class="text-sm font-bold">Rincian item</h2>
                    <button type="button" class="flex items-center gap-1 text-xs font-bold text-primary-600 hover:text-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500" @click="addItem">
                        <Plus class="size-3.5" /> Tambah item
                    </button>
                </div>

                <div class="space-y-3">
                    <div v-for="(item, index) in form.items" :key="index" class="rounded-2xl border border-slate-200 bg-white p-3">
                        <div class="flex items-start gap-2">
                            <div class="min-w-0 flex-1">
                                <label :for="`item-desc-${index}`" class="sr-only">Deskripsi item {{ index + 1 }}</label>
                                <input :id="`item-desc-${index}`" v-model="item.description" type="text" required placeholder="Deskripsi layanan / produk" class="block w-full rounded-xl border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700 placeholder:text-slate-300 focus:border-primary-500 focus:ring-primary-500" />
                            </div>
                            <button v-if="form.items.length > 1" type="button" :aria-label="`Hapus item ${index + 1}`" class="flex size-10 shrink-0 items-center justify-center rounded-xl text-slate-400 hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500" @click="removeItem(index)">
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                        <div class="mt-2 flex items-center rounded-xl border border-slate-200 px-3 focus-within:border-primary-500 focus-within:ring-1 focus-within:ring-primary-500">
                            <span class="text-sm font-semibold text-slate-400">Rp</span>
                            <label :for="`item-amount-${index}`" class="sr-only">Nominal item {{ index + 1 }}</label>
                            <input :id="`item-amount-${index}`" v-model.number="item.amount" type="number" min="1" step="any" required placeholder="0" class="w-full border-0 px-2 py-2.5 text-sm font-bold tabular-nums text-slate-700 placeholder:text-slate-300 focus:ring-0" />
                        </div>
                        <p v-if="form.errors[`items.${index}.description`]" class="mt-1.5 text-xs font-semibold text-rose-600">{{ form.errors[`items.${index}.description`] }}</p>
                        <p v-if="form.errors[`items.${index}.amount`]" class="mt-1 text-xs font-semibold text-rose-600">{{ form.errors[`items.${index}.amount`] }}</p>
                    </div>
                </div>
                <p v-if="form.errors.items" class="mt-2 text-xs font-semibold text-rose-600">{{ form.errors.items }}</p>
            </section>

            <Card label="Total invoice" :amount="formattedTotal" />

            <button type="submit" :disabled="form.processing" class="flex min-h-12 w-full items-center justify-center rounded-xl bg-primary-600 px-4 py-3 text-sm font-bold text-white shadow-sm shadow-primary-200 transition hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 disabled:opacity-50">
                {{ form.processing ? 'Menyimpan…' : 'Simpan invoice' }}
            </button>
        </form>
    </PrototypeLayout>
</template>
