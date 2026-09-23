<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, BookOpen, Landmark, Plus, TrendingUp } from '@lucide/vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const steps = [
    {
        title: 'Atur modal awal',
        description: 'Masukkan modal awal dan periode usaha sebagai baseline perhitungan.',
        label: 'Buka Modal / Kas',
        href: route('capital.index'),
        icon: Landmark,
    },
    {
        title: 'Catat transaksi',
        description: 'Pilih pemasukan atau pengeluaran, lalu isi nominal, tanggal, dan kategori.',
        label: 'Tambah transaksi',
        href: route('transactions.create'),
        icon: Plus,
    },
    {
        title: 'Baca laporan',
        description: 'Gunakan laporan P&L untuk melihat pemasukan, pengeluaran, dan laba bersih.',
        label: 'Lihat laporan P&L',
        href: route('reports.profit-loss'),
        icon: TrendingUp,
    },
];
</script>

<template>
    <Head title="Panduan mulai" />

    <PrototypeLayout>
        <section class="pb-5 pt-4">
            <PageHeader title="Panduan mulai" :back="route('dashboard')" />
        </section>

        <section class="rounded-3xl bg-primary-600 p-5 text-white shadow-lg shadow-primary-200" aria-labelledby="guide-intro-title">
            <BookOpen class="size-7 text-primary-100" aria-hidden="true" />
            <h2 id="guide-intro-title" class="mt-4 text-xl font-extrabold tracking-tight">Mulai catat keuangan usaha</h2>
            <p class="mt-2 text-sm leading-6 text-primary-100">Ikuti tiga langkah sederhana untuk menyiapkan pencatatan dan membaca hasil usaha.</p>
        </section>

        <section class="mt-6" aria-labelledby="guide-steps-title">
            <h2 id="guide-steps-title" class="mb-3 text-sm font-bold">Urutan yang disarankan</h2>
            <div class="space-y-3">
                <Link
                    v-for="(step, index) in steps"
                    :key="step.title"
                    :href="step.href"
                    class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-primary-200 hover:bg-primary-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                >
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-sm font-extrabold text-primary-600">{{ index + 1 }}</span>
                    <span class="min-w-0 flex-1">
                        <strong class="block text-sm font-bold text-slate-800">{{ step.title }}</strong>
                        <span class="mt-1 block text-xs leading-5 text-slate-500">{{ step.description }}</span>
                        <span class="mt-2 inline-flex items-center gap-1 text-xs font-bold text-primary-600">{{ step.label }} <ArrowRight class="size-3.5" /></span>
                    </span>
                    <component :is="step.icon" class="mt-1 size-5 shrink-0 text-primary-500" aria-hidden="true" />
                </Link>
            </div>
        </section>

        <section class="mt-6 pb-8" aria-labelledby="guide-basics-title">
            <h2 id="guide-basics-title" class="mb-3 text-sm font-bold">Dasar pencatatan</h2>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 text-xs leading-6 text-slate-600">
                <p><strong class="text-slate-800">Pemasukan</strong> adalah uang yang diterima usaha, seperti penjualan atau jasa.</p>
                <p class="mt-2"><strong class="text-slate-800">Pengeluaran</strong> adalah biaya usaha, seperti stok, gaji, sewa, atau operasional.</p>
                <p class="mt-2"><strong class="text-slate-800">Laba bersih</strong> dihitung dari pemasukan dikurangi pengeluaran. Top-up modal ditampilkan terpisah karena bukan laba operasional.</p>
            </div>
        </section>
    </PrototypeLayout>
</template>
