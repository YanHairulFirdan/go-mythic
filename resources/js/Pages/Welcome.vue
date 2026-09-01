<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Sparta Ledger — Pencatatan Keuangan Usaha Kecil" />

    <main class="flex min-h-dvh flex-col items-center justify-center bg-[#f7f8fc] px-6 py-12 text-slate-900">
        <div class="w-full max-w-sm text-center">
            <div class="flex items-center justify-center gap-2">
                <span class="flex size-11 items-center justify-center rounded-2xl bg-primary-600 text-2xl font-black text-white shadow-sm shadow-primary-200">✦</span>
                <span class="text-lg font-extrabold tracking-tight">Sparta Ledger</span>
            </div>

            <h1 class="mt-6 text-2xl font-bold leading-snug tracking-tight">
                Kelola modal, transaksi, dan invoice usaha kecil Anda dalam satu tempat.
            </h1>
            <p class="mt-3 text-sm text-slate-500">
                Pantau kas berjalan, catat pemasukan &amp; pengeluaran, lalu lihat laba bersih tanpa spreadsheet.
            </p>

            <div class="mt-8 space-y-3">
                <Link
                    :href="route('login')"
                    class="flex min-h-12 w-full items-center justify-center rounded-xl bg-primary-600 px-4 py-3 text-sm font-bold text-white shadow-sm shadow-primary-200 transition hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2"
                >
                    Masuk
                </Link>
                <Link
                    :href="route('register')"
                    class="flex min-h-12 w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 transition hover:border-primary-200 hover:text-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2"
                >
                    Daftar
                </Link>
            </div>

            <p class="mt-6 text-xs text-slate-400">Mulai Rp99.000/bulan &middot; tanpa kontrak</p>
        </div>
    </main>
</template>
