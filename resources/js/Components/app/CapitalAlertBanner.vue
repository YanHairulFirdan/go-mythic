<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

interface PageProps {
    auth: { user: { role?: string } | null };
    capitalActive: boolean;
    [key: string]: unknown;
}

const page = usePage<PageProps>();

// US-MK-05 AC3: Owner gets a "Set Modal" shortcut; Employee is only told to
// contact the Owner. AC2: non-removable — no dismiss control here.
const isOwner = computed((): boolean => page.props.auth.user?.role === 'owner');

const capitalHref = route('capital.index');
</script>

<template>
    <div
        v-if="!page.props.capitalActive"
        role="alert"
        class="flex shrink-0 items-start gap-3 border-b border-rose-200 bg-rose-50 px-5 py-3 text-rose-800"
    >
        <span class="mt-0.5 text-base font-black leading-none" aria-hidden="true">!</span>
        <div class="min-w-0 flex-1">
            <p class="text-xs font-bold">Belum ada modal aktif</p>
            <p class="mt-0.5 text-[11px] leading-snug text-rose-700">
                <template v-if="isOwner">
                    Setiap transaksi butuh modal/kas aktif sebagai baseline.
                </template>
                <template v-else>
                    Hubungi Owner untuk mengatur modal/kas usaha.
                </template>
            </p>
            <Link
                v-if="isOwner"
                :href="capitalHref"
                class="mt-2 inline-flex items-center rounded-lg bg-rose-600 px-2.5 py-1 text-[11px] font-bold text-white transition hover:bg-rose-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500"
            >
                Set Modal Sekarang
            </Link>
        </div>
    </div>
</template>
