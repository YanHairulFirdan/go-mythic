<script setup>
import { FileText, Home, MoreHorizontal, ReceiptText, Users } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';

const items = [
    { label: 'Beranda', href: route('dashboard'), icon: Home },
    { label: 'Transaksi', href: route('transactions.index'), icon: FileText },
    { label: 'Customer', href: route('customers.index'), icon: Users },
    { label: 'Invoice', href: route('invoices.index'), icon: ReceiptText },
    { label: 'Lainnya', href: '#', icon: MoreHorizontal },
];

const props = defineProps({ active: { type: String, default: '' } });
const currentRoute = route().current();
const currentActive = currentRoute?.startsWith('dashboard')
    ? 'Beranda'
    : currentRoute?.startsWith('transactions')
        ? 'Transaksi'
        : currentRoute?.startsWith('customers')
            ? 'Customer'
            : currentRoute?.startsWith('invoices')
                ? 'Invoice'
                : props.active;
</script>

<template>
    <nav aria-label="Navigasi utama" class="fixed inset-x-0 bottom-0 z-10 border-t border-slate-200 bg-white/95 px-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] pt-2 backdrop-blur sm:absolute">
        <div class="mx-auto grid max-w-md grid-cols-5 gap-1">
            <Link
                v-for="item in items"
                :key="item.label"
                :href="item.href"
                :class="[
                    'flex min-h-12 flex-col items-center justify-center gap-1 rounded-xl text-[10px] font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500',
                    currentActive === item.label ? 'bg-indigo-50 text-indigo-600' : 'text-slate-400 hover:bg-slate-50 hover:text-slate-600',
                ]"
            >
                <component :is="item.icon" class="size-[18px]" :stroke-width="currentActive === item.label ? 2.5 : 2" />
                {{ item.label }}
            </Link>
        </div>
    </nav>
</template>
