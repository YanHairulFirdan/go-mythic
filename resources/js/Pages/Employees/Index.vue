<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import { ref } from 'vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';
import Card from '@/Components/ui/Card.vue';

const props = defineProps({
    employees: {
        type: Array,
        required: true,
    },
    canCreateEmployee: {
        type: Boolean,
        required: true,
    },
});

const modal = ref(null);

const workerForm = useForm({ name: '' });
const employeeForm = useForm({ name: '', username: '', password: '' });

const closeModal = () => {
    modal.value = null;
    workerForm.clearErrors();
    employeeForm.clearErrors();
};

const submitWorker = () => {
    workerForm.post(route('employees.store'), {
        preserveScroll: true,
        onSuccess: () => {
            workerForm.reset();
            modal.value = null;
        },
    });
};

const submitEmployee = () => {
    employeeForm.post(route('employees.account.store'), {
        preserveScroll: true,
        onSuccess: () => {
            employeeForm.reset();
            modal.value = null;
        },
    });
};
</script>

<template>
    <Head title="Kelola Karyawan" />

    <PrototypeLayout>
        <section class="flex items-center gap-3 pb-5 pt-4">
            <Link
                :href="route('dashboard')"
                aria-label="Kembali ke beranda"
                class="flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:border-indigo-200 hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <ChevronLeft class="size-5" />
            </Link>
            <h1 class="text-xl font-bold tracking-tight">Kelola Karyawan</h1>
        </section>

        <Card
            label="Karyawan terdaftar"
            :amount="String(props.employees.length)"
            :note="props.canCreateEmployee ? 'Paket Paid · employee ber-akun tersedia' : 'Paket Free · hanya worker tanpa akun'"
        />

        <div v-if="!props.canCreateEmployee" class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-3.5 text-sm text-amber-800">
            Paket Free hanya dapat menambah worker tanpa akun login. Upgrade ke Paid untuk employee ber-akun.
        </div>

        <section class="mt-4" aria-label="Daftar karyawan">
            <Link
                v-for="employee in props.employees"
                :key="employee.id"
                :href="route('employees.show', employee.id)"
                class="flex items-center gap-3 border-b border-slate-100 py-3.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500"
            >
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-xs font-bold text-indigo-700">
                    {{ employee.name?.charAt(0) ?? '?' }}
                </span>
                <span class="min-w-0 flex-1">
                    <strong class="block truncate text-xs font-bold text-slate-800">{{ employee.name }}</strong>
                    <small class="mt-1 block truncate text-[10px] text-slate-400">
                        {{ employee.has_access_to_system ? `Bisa login · ${employee.user?.username ?? ''}` : 'Worker · tanpa akun login' }}
                    </small>
                </span>
                <span
                    :class="employee.status === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'"
                    class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold capitalize"
                >
                    {{ employee.status }}
                </span>
            </Link>
            <p v-if="props.employees.length === 0" class="py-10 text-center text-sm text-slate-400">
                Belum ada karyawan.
            </p>
        </section>

        <div class="grid gap-3 pb-8 pt-5">
            <button
                type="button"
                class="flex min-h-12 w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                @click="modal = 'worker'"
            >
                + Tambah worker
            </button>
            <button
                v-if="props.canCreateEmployee"
                type="button"
                class="flex min-h-12 w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-sm shadow-indigo-200 transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                @click="modal = 'employee'"
            >
                + Tambah employee ber-akun
            </button>
            <Link
                v-else
                :href="route('subscription.index')"
                class="flex min-h-12 w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-sm shadow-indigo-200 transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
            >
                Upgrade ke Paid untuk employee ber-akun
            </Link>
        </div>

        <div v-if="modal === 'worker'" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center" role="presentation" @click.self="closeModal">
            <section class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="worker-title">
                <h2 id="worker-title" class="text-lg font-bold text-slate-800">Tambah worker</h2>
                <p class="mt-1 text-xs text-slate-500">Pekerja harian tanpa akun login — cukup nama.</p>
                <form class="mt-4 space-y-4" @submit.prevent="submitWorker">
                    <div>
                        <label for="worker-name" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Nama</label>
                        <input id="worker-name" v-model="workerForm.name" type="text" required autocomplete="name" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 placeholder:text-slate-300 focus:border-indigo-500 focus:ring-indigo-500" />
                        <p v-if="workerForm.errors.name" class="mt-1.5 text-xs font-semibold text-rose-600">{{ workerForm.errors.name }}</p>
                    </div>
                    <div class="flex gap-3 pt-1">
                        <button type="button" class="flex min-h-11 flex-1 items-center justify-center rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500" @click="closeModal">Batal</button>
                        <button type="submit" :disabled="workerForm.processing" class="flex min-h-11 flex-1 items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-50">Tambah worker</button>
                    </div>
                </form>
            </section>
        </div>

        <div v-if="modal === 'employee'" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center" role="presentation" @click.self="closeModal">
            <section class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="employee-title">
                <h2 id="employee-title" class="text-lg font-bold text-slate-800">Tambah employee ber-akun</h2>
                <p class="mt-1 text-xs text-slate-500">Employee mendapat akun untuk masuk ke aplikasi.</p>
                <form class="mt-4 space-y-4" @submit.prevent="submitEmployee">
                    <div>
                        <label for="employee-name" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Nama</label>
                        <input id="employee-name" v-model="employeeForm.name" type="text" required autocomplete="name" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 placeholder:text-slate-300 focus:border-indigo-500 focus:ring-indigo-500" />
                        <p v-if="employeeForm.errors.name" class="mt-1.5 text-xs font-semibold text-rose-600">{{ employeeForm.errors.name }}</p>
                    </div>
                    <div>
                        <label for="employee-username" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Username</label>
                        <input id="employee-username" v-model="employeeForm.username" type="text" required autocomplete="username" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 placeholder:text-slate-300 focus:border-indigo-500 focus:ring-indigo-500" />
                        <p v-if="employeeForm.errors.username" class="mt-1.5 text-xs font-semibold text-rose-600">{{ employeeForm.errors.username }}</p>
                    </div>
                    <div>
                        <label for="employee-password" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Kata sandi</label>
                        <input id="employee-password" v-model="employeeForm.password" type="password" required minlength="8" autocomplete="new-password" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 placeholder:text-slate-300 focus:border-indigo-500 focus:ring-indigo-500" />
                        <p v-if="employeeForm.errors.password" class="mt-1.5 text-xs font-semibold text-rose-600">{{ employeeForm.errors.password }}</p>
                    </div>
                    <div class="flex gap-3 pt-1">
                        <button type="button" class="flex min-h-11 flex-1 items-center justify-center rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500" @click="closeModal">Batal</button>
                        <button type="submit" :disabled="employeeForm.processing" class="flex min-h-11 flex-1 items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-50">Tambah employee</button>
                    </div>
                </form>
            </section>
        </div>
    </PrototypeLayout>
</template>
