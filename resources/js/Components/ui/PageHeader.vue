<script setup>
import { ChevronLeft } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    title: { type: String, required: true },
    back: { type: String, default: '' },
});
</script>

<template>
    <div class="flex items-center gap-3">
        <Link v-if="back" :href="back" aria-label="Kembali" class="flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:border-indigo-200 hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
            <ChevronLeft class="size-5" />
        </Link>
        <h1 class="text-xl font-bold tracking-tight">
            {{ title }}
        </h1>
    </div>
</template>
