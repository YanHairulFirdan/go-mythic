<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';

const props = defineProps({
    employee: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: props.employee.name ?? '',
});

const submit = () => {
    form.patch(route('employees.update', props.employee.id));
};
</script>

<template>
    <Head title="Edit karyawan" />

    <PrototypeLayout>
        <section class="flex items-center gap-3 pb-5 pt-4">
            <Link
                :href="route('employees.show', props.employee.id)"
                aria-label="Kembali ke detail karyawan"
                class="flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:border-primary-200 hover:text-primary-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
                <ChevronLeft class="size-5" />
            </Link>
            <h1 class="text-xl font-bold tracking-tight">Edit karyawan</h1>
        </section>

        <form class="space-y-4 pb-8" @submit.prevent="submit">
            <div>
                <label for="name" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Nama</label>
                <input id="name" v-model="form.name" type="text" required autocomplete="name" placeholder="Nama karyawan" class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 placeholder:text-slate-300 focus:border-primary-500 focus:ring-primary-500" />
                <p v-if="form.errors.name" class="mt-1.5 text-xs font-semibold text-rose-600">{{ form.errors.name }}</p>
            </div>

            <button type="submit" :disabled="form.processing" class="flex min-h-12 w-full items-center justify-center rounded-xl bg-primary-600 px-4 py-3 text-sm font-bold text-white shadow-sm shadow-primary-200 transition hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 disabled:opacity-50">
                {{ form.processing ? 'Menyimpan…' : 'Simpan perubahan' }}
            </button>
        </form>
    </PrototypeLayout>
</template>
