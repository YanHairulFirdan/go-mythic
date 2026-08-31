<script setup>
defineProps({
    variant: { type: String, default: 'primary' },
    as: { type: String, default: 'button' },
});
</script>

<template>
    <component
        :is="as"
        :class="[
            'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
            {
                'bg-indigo-600 text-white shadow-sm shadow-indigo-200 hover:bg-indigo-700': variant === 'primary',
                'border border-slate-200 bg-white text-slate-700 hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700': variant === 'outline',
                'text-indigo-600 hover:bg-indigo-50': variant === 'ghost',
            },
        ]"
    >
        <slot />
    </component>
</template>
