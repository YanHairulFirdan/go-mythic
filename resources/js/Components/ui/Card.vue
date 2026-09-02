<script setup>
defineProps({
    label: { type: String, default: '' },
    amount: { type: String, default: '' },
    note: { type: String, default: '' },
});
</script>

<template>
    <div class="rounded-2xl border border-slate-200 bg-white p-4">
        <div v-if="label" class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
            {{ label }}
        </div>
        <div v-if="amount" class="mt-1 text-xl font-bold tabular-nums tracking-tight">
            {{ amount }}
        </div>
        <slot />
        <div v-if="note" class="mt-2 text-xs text-slate-500">
            {{ note }}
        </div>
    </div>
</template>
