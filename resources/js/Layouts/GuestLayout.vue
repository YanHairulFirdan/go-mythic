<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-[#f3f2fb] pt-6 font-sans text-[#172033] sm:justify-center sm:pt-0"
    >
        <div>
            <Link href="/">
                <ApplicationLogo class="h-20 w-20 fill-current text-[#4f46e5]" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden rounded-[15px] border border-[#e8eaf1] bg-white px-6 py-5 shadow-[0_10px_24px_rgba(31,28,74,0.08)] sm:max-w-md"
        >
            <slot />
        </div>
    </div>
</template>
