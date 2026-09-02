<script setup lang="ts">
import { computed } from 'vue';

type QuotaState = 'normal' | 'warning' | 'full';

const props = defineProps<{
    used: number;
    limit: number;
    label: string;
    state: QuotaState;
}>();

// US-TR-01B AC3: ring colour follows the design token per band.
const RING_COLOR: Record<QuotaState, string> = {
    normal: '#4F46E5', // Indigo
    warning: '#F59E0B', // Amber
    full: '#F43F5E', // Rose
};

const RADIUS = 42;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

const progress = computed((): number => {
    if (props.limit <= 0) {
        return 0;
    }

    return Math.min(1, Math.max(0, props.used / props.limit));
});

// AC2: ring fills proportionally (120/150 -> ~80% of the circle).
const dashOffset = computed((): number => CIRCUMFERENCE * (1 - progress.value));
const ringColor = computed((): string => RING_COLOR[props.state]);
</script>

<template>
    <div class="flex flex-col items-center gap-2">
        <div
            class="relative size-24"
            role="img"
            :aria-label="`Kuota ${label}: ${used} dari ${limit} transaksi hari ini`"
        >
            <svg class="size-full -rotate-90" viewBox="0 0 100 100">
                <circle
                    cx="50"
                    cy="50"
                    :r="RADIUS"
                    fill="none"
                    stroke="#E2E8F0"
                    stroke-width="9"
                />
                <circle
                    cx="50"
                    cy="50"
                    :r="RADIUS"
                    fill="none"
                    :stroke="ringColor"
                    stroke-width="9"
                    stroke-linecap="round"
                    :stroke-dasharray="CIRCUMFERENCE"
                    :stroke-dashoffset="dashOffset"
                    class="transition-[stroke-dashoffset] duration-500"
                />
            </svg>
            <div class="absolute inset-0 flex items-center justify-center">
                <!-- AC4: n/150 uses the design-system sans font, no display face. -->
                <span class="font-sans text-sm font-bold tabular-nums text-slate-900">
                    {{ used }}<span class="text-slate-400">/{{ limit }}</span>
                </span>
            </div>
        </div>
        <span class="font-sans text-[11px] font-semibold uppercase tracking-wider text-slate-500">
            {{ label }}
        </span>
    </div>
</template>
