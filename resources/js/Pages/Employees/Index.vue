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
});

const form = useForm({
    name: '',
});

const addWorker = () => {
    form.post(route('employees.store'), {
        onSuccess: () => form.reset(),
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
                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <h3 class="text-lg font-medium text-[#172033]">
                        Tambah Worker
                    </h3>
                    <p class="mt-1 text-sm text-[#7b8498]">
                        Pekerja harian tanpa akun login — cukup nama, tanpa username/kata sandi.
                    </p>

                    <form
                        class="mt-4 flex max-w-xl items-end gap-3"
                        @submit.prevent="addWorker"
                    >
                        <div class="flex-1">
                            <InputLabel
                                for="name"
                                value="Nama worker"
                            />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                :class="{ 'border-red-500': form.errors.name }"
                                required
                                autocomplete="off"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.name"
                            />
                        </div>
                        <PrimaryButton
                            type="submit"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                        >
                            Tambah Worker
                        </PrimaryButton>
                    </form>
                </div>

                <div class="bg-white shadow sm:rounded-lg">
                    <table class="w-full text-left text-sm text-[#172033]">
                        <thead class="border-b border-[#e8eaf1] text-xs uppercase text-[#7b8498]">
                            <tr>
                                <th class="px-6 py-3">Nama</th>
                                <th class="px-6 py-3">Status Akses</th>
                                <th class="px-6 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="employee in employees"
                                :key="employee.id"
                                class="border-b border-[#e8eaf1] last:border-0"
                            >
                                <td class="px-6 py-4">{{ employee.name }}</td>
                                <td class="px-6 py-4">
                                    <span
                                        v-if="employee.has_access_to_system"
                                        class="inline-flex rounded-full bg-[#e8f5f0] px-2.5 py-0.5 text-xs font-semibold text-[#159570]"
                                    >
                                        Bisa login
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex rounded-full bg-[#fff8e8] px-2.5 py-0.5 text-xs font-semibold text-[#a96e13]"
                                    >
                                        Tanpa akun login
                                    </span>
                                </td>
                                <td class="px-6 py-4 capitalize">{{ employee.status }}</td>
                            </tr>
                            <tr v-if="employees.length === 0">
                                <td
                                    colspan="3"
                                    class="px-6 py-8 text-center text-sm text-[#7b8498]"
                                >
                                    Belum ada karyawan atau worker.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
