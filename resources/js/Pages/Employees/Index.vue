<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    employees: {
        type: Array,
        required: true,
    },
    canCreateEmployee: {
        type: Boolean,
        required: true,
    },
});

const workerForm = useForm({
    name: '',
});

const employeeForm = useForm({
    name: '',
    username: '',
    password: '',
});

const submitWorker = () => {
    workerForm.post(route('employees.store'), {
        onSuccess: () => workerForm.reset(),
    });
};

const submitEmployee = () => {
    if (!canCreateEmployee) {
        window.location.href = route('subscription.index');
        return;
    }

    employeeForm.post(route('employees.account.store'), {
        onSuccess: () => employeeForm.reset(),
    });
};
</script>

<template>
    <Head title="Kelola Karyawan" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Kelola Karyawan
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="grid gap-6 lg:grid-cols-2">
                    <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900">
                            Tambah Employee
                        </h3>
                        <p class="mt-1 text-sm text-gray-600">
                            Employee memiliki akun untuk masuk ke aplikasi.
                        </p>
                        <p
                            v-if="!canCreateEmployee"
                            class="mt-3 rounded-md bg-amber-50 p-3 text-sm text-amber-700"
                        >
                            Paket Free belum dapat membuat Employee ber-akun.
                            Upgrade ke Paid untuk melanjutkan.
                        </p>

                        <form class="mt-4 space-y-4" @submit.prevent="submitEmployee">
                            <div>
                                <InputLabel for="employee-name" value="Nama" />
                                <TextInput
                                    id="employee-name"
                                    v-model="employeeForm.name"
                                    class="mt-1 block w-full"
                                    required
                                    autocomplete="name"
                                />
                                <InputError class="mt-2" :message="employeeForm.errors.name" />
                            </div>
                            <div>
                                <InputLabel for="employee-username" value="Username" />
                                <TextInput
                                    id="employee-username"
                                    v-model="employeeForm.username"
                                    class="mt-1 block w-full"
                                    required
                                    autocomplete="username"
                                />
                                <InputError class="mt-2" :message="employeeForm.errors.username" />
                            </div>
                            <div>
                                <InputLabel for="employee-password" value="Password" />
                                <TextInput
                                    id="employee-password"
                                    v-model="employeeForm.password"
                                    type="password"
                                    class="mt-1 block w-full"
                                    required
                                    minlength="8"
                                    autocomplete="new-password"
                                />
                                <InputError class="mt-2" :message="employeeForm.errors.password" />
                            </div>
                            <PrimaryButton
                                :class="{ 'opacity-25': employeeForm.processing }"
                                :disabled="employeeForm.processing"
                            >
                                {{ canCreateEmployee ? 'Tambah Employee' : 'Upgrade ke Paid' }}
                            </PrimaryButton>
                        </form>
                    </section>

                    <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900">
                            Tambah Worker
                        </h3>
                        <p class="mt-1 text-sm text-gray-600">
                            Pekerja harian tanpa akun login — cukup nama, tanpa username/kata sandi.
                        </p>
                        <form class="mt-4" @submit.prevent="submitWorker">
                            <InputLabel for="worker-name" value="Nama" />
                            <TextInput
                                id="worker-name"
                                v-model="workerForm.name"
                                class="mt-1 block w-full"
                                required
                                autocomplete="name"
                            />
                            <InputError class="mt-2" :message="workerForm.errors.name" />
                            <div class="mt-4 flex justify-end">
                                <PrimaryButton
                                    :class="{ 'opacity-25': workerForm.processing }"
                                    :disabled="workerForm.processing"
                                >
                                    Tambah Worker
                                </PrimaryButton>
                            </div>
                        </form>
                    </section>
                </div>

                <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900">Roster Karyawan</h3>
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        <th class="px-3 py-3">Nama</th>
                                        <th class="px-3 py-3">Status Akses</th>
                                        <th class="px-3 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                                    <tr v-for="employee in employees" :key="employee.id">
                                        <td class="whitespace-nowrap px-3 py-3 font-medium">{{ employee.name }}</td>
                                        <td class="whitespace-nowrap px-3 py-3">
                                            <span
                                                :class="employee.has_access_to_system ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                                                class="inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                                            >
                                                {{ employee.has_access_to_system ? `Bisa login (${employee.user?.username ?? ''})` : 'Tanpa akun login' }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3">
                                            <span
                                                :class="employee.status === 'active' ? 'text-green-700' : 'text-red-700'"
                                                class="font-medium"
                                            >
                                                {{ employee.status }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="employees.length === 0">
                                        <td colspan="3" class="px-3 py-8 text-center text-gray-500">
                                            Belum ada karyawan.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
