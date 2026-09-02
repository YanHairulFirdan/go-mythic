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
            'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
            {
                'bg-primary-600 text-white shadow-sm shadow-primary-200 hover:bg-primary-700': variant === 'primary',
                'border border-slate-200 bg-white text-slate-700 hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700': variant === 'outline',
                'text-primary-600 hover:bg-primary-50': variant === 'ghost',
            },
        ]"
    >
        <slot />
    </component>
</template>
