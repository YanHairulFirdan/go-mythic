<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Lupa kata sandi" />

        <div class="mb-4">
            <h1 class="text-[19px] font-extrabold tracking-[-0.03em] text-[#172033]">
                Lupa kata sandi?
            </h1>
            <p class="mt-1 text-[11px] text-[#7b8498]">
                Masukkan email akunmu. Kami kirim tautan untuk atur ulang kata sandi.
            </p>
        </div>

        <div
            v-if="status"
            class="mb-4 rounded-[11px] border border-[#f6dfae] bg-[#fff8e8] p-2.5 text-[11px] text-[#a96e13]"
            role="status"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <label
                    for="email"
                    class="mb-1 block text-[10px] font-extrabold uppercase tracking-[0.06em] text-[#636b7d]"
                >
                    Email
                </label>
                <TextInput
                    id="email"
                    type="email"
                    :class="[
                        'block w-full rounded-[10px] px-3 py-[11px] text-xs text-[#172033] shadow-none focus:ring-[#4f46e5]',
                        form.errors.email ? 'border-[#d94b5b] focus:border-[#d94b5b]' : 'border-[#e8eaf1] focus:border-[#4f46e5]',
                    ]"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <PrimaryButton
                class="mt-4 flex min-h-[42px] w-full justify-center rounded-[11px] bg-[#4f46e5] px-3 py-2.5 text-[11px] font-extrabold uppercase tracking-[0.02em] shadow-[0_4px_12px_rgba(31,28,74,0.12)] transition hover:bg-[#3730a3] focus:bg-[#3730a3] focus:ring-[#a9a5ff] active:bg-[#3730a3]"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
            >
                Kirim tautan reset
            </PrimaryButton>
        </form>

        <div class="mt-4 text-center text-[11px] text-[#7b8498]">
            Ingat kata sandi?
            <Link
                :href="route('login')"
                class="font-extrabold text-[#4f46e5] hover:text-[#3730a3] focus:outline-none focus:ring-2 focus:ring-[#a9a5ff] focus:ring-offset-2"
            >
                Kembali ke Masuk
            </Link>
        </div>
    </GuestLayout>
</template>
