<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ChevronDown, ChevronLeft } from '@lucide/vue';
import { ref } from 'vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';

defineProps({
    entries: {
        type: Array,
        default: () => [],
    },
});

const expanded = ref(new Set());

const toggle = (id) => {
    const next = new Set(expanded.value);
    next.has(id) ? next.delete(id) : next.add(id);
    expanded.value = next;
};

const formatRupiah = (value) => `Rp${Number(value || 0).toLocaleString('id-ID')}`;
const formatDate = (value) => (value
    ? new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
    : '—');
</script>

<template>
    <Head title="Riwayat Modal" />

    <PrototypeLayout>
        <section class="flex items-center gap-3 pb-5 pt-4">
            <Link
                :href="route('capital.index')"
                aria-label="Kembali ke Modal / Kas"
                class="flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:border-indigo-200 hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <ChevronLeft class="size-5" />
            </Link>
            <h1 class="text-xl font-bold tracking-tight">Riwayat modal</h1>
        </section>

        <section class="space-y-3 pb-8" aria-label="Daftar entry modal">
            <article
                v-for="entry in entries"
                :key="entry.id"
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white"
            >
                <button
                    type="button"
                    class="flex w-full items-center gap-3 p-4 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500"
                    :aria-expanded="expanded.has(entry.id)"
                    @click="toggle(entry.id)"
                >
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-2">
                            <strong class="text-sm font-bold tabular-nums text-slate-800">{{ formatRupiah(entry.final_amount) }}</strong>
                            <span
                                class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                                :class="entry.status === 'Aktif' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-200 text-slate-700'"
                            >
                                {{ entry.status }}
                            </span>
                        </span>
                        <small class="mt-1 block text-[10px] text-slate-400">
                            {{ formatDate(entry.start_date) }} – {{ formatDate(entry.end_date) }} · dibuat {{ formatDate(entry.created_at) }}
                        </small>
                    </span>
                    <ChevronDown
                        class="size-4 shrink-0 text-slate-300 transition"
                        :class="expanded.has(entry.id) && 'rotate-180'"
                    />
                </button>

                <div v-if="expanded.has(entry.id)" class="border-t border-slate-100 bg-slate-50/60 px-4 py-3">
                    <p class="mb-2 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Riwayat top-up</p>
                    <ul v-if="entry.topups.length" class="space-y-2">
                        <li v-for="topup in entry.topups" :key="topup.id" class="flex items-center justify-between gap-3 text-xs">
                            <span class="text-slate-500">
                                {{ formatDate(topup.changed_at) }}
                                <span v-if="topup.extended_end_date" class="text-slate-400">· perpanjang ke {{ formatDate(topup.extended_end_date) }}</span>
                            </span>
                            <strong class="font-semibold tabular-nums text-slate-700">+{{ formatRupiah(topup.amount) }}</strong>
                        </li>
                    </ul>
                    <p v-else class="text-xs text-slate-400">Belum ada top-up.</p>
                </div>
            </article>

            <p v-if="entries.length === 0" class="rounded-2xl border border-slate-200 bg-white py-10 text-center text-sm text-slate-400">
                Belum ada riwayat modal.
            </p>
        </section>
    </PrototypeLayout>
</template>
