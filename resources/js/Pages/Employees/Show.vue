<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';

interface Employee {
    id: number;
    name: string;
    has_access_to_system: boolean;
    status: string;
}

interface Breakdown {
    total: number;
    count: number;
}

interface Props {
    employee: Employee;
    breakdown: Breakdown;
}

const props = defineProps<Props>();

const backHref = route('employees.index');

const formatRupiah = (value: number): string => `Rp${Number(value).toLocaleString('id-ID')}`;
</script>

<template>
    <Head title="Detail karyawan" />

    <PrototypeLayout>
        <section class="flex items-center gap-3 pb-5 pt-4">
            <Link
                :href="backHref"
                aria-label="Kembali ke daftar karyawan"
                class="flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:border-primary-200 hover:text-primary-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
                <ChevronLeft class="size-5" />
            </Link>
            <h1 class="min-w-0 truncate text-xl font-bold tracking-tight">{{ props.employee.name }}</h1>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4" aria-label="Info karyawan">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs text-slate-500">
                    {{ props.employee.has_access_to_system ? 'Employee ber-akun' : 'Worker tanpa akun login' }}
                </span>
                <span
                    :class="props.employee.status === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'"
                    class="rounded-full px-2 py-0.5 text-[10px] font-bold capitalize"
                >
                    {{ props.employee.status }}
                </span>
            </div>
        </section>

        <!-- US-CUST-04: breakdown transaksi (SUM + COUNT di mana employee_id = orang ini) -->
        <section class="mt-4 grid grid-cols-2 gap-2" aria-label="Ringkasan transaksi">
            <div class="rounded-xl border border-slate-200 bg-white p-3">
                <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total nominal</div>
                <div class="mt-1 text-sm font-bold tabular-nums text-slate-800">{{ formatRupiah(props.breakdown.total) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-3">
                <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Jumlah transaksi</div>
                <div class="mt-1 text-sm font-bold tabular-nums text-slate-800">{{ props.breakdown.count }}</div>
            </div>
        </section>
        <p class="mt-2 px-1 text-[11px] text-slate-400">
            Dihitung dari transaksi yang mencatat karyawan ini sebagai pelaksana.
        </p>
    </PrototypeLayout>
</template>
