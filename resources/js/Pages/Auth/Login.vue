<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    error: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Masuk" />

        <div class="mb-4">
            <h1 class="text-[19px] font-extrabold tracking-[-0.03em] text-[#172033]">
                Masuk ke akun
            </h1>
        </div>

        <div class="mb-5 rounded-[15px] border border-[#e8eaf1] bg-white p-[14px] text-center">
            <div class="text-[28px] font-extrabold leading-none text-[#4f46e5]" aria-hidden="true">✦</div>
            <div class="mt-1 text-[21px] font-extrabold tracking-[-0.04em] text-[#172033]">
                Sparta Ledger
            </div>
            <p class="mt-1 text-[11px] text-[#7b8498]">Catat keuangan tokomu, kapan saja</p>
        </div>

        <div
            v-if="status"
            class="mb-4 rounded-[11px] border border-[#f6dfae] bg-[#fff8e8] p-2.5 text-[11px] text-[#a96e13]"
            role="status"
        >
            {{ status }}
        </div>

        <div
            v-if="error"
            class="mb-4 rounded-[11px] border border-[#f5c7cd] bg-[#fff0f2] p-2.5 text-[11px] text-[#d94b5b]"
            role="alert"
        >
            {{ error }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email atau username" />

                <TextInput
                    id="email"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <label
                    for="password"
                    class="mb-1 mt-3 block text-[10px] font-extrabold uppercase tracking-[0.06em] text-[#636b7d]"
                >
                    Kata sandi
                </label>

                <TextInput
                    id="password"
                    type="password"
                    class="block w-full rounded-[10px] border-[#e8eaf1] px-3 py-[11px] text-xs text-[#172033] shadow-none focus:border-[#4f46e5] focus:ring-[#4f46e5]"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-3 flex items-center justify-between">
                <label class="flex items-center text-[11px] text-[#7b8498]">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2">Ingat saya</span>
                </label>

                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-[11px] font-extrabold text-[#4f46e5] hover:text-[#3730a3] focus:outline-none focus:ring-2 focus:ring-[#a9a5ff] focus:ring-offset-2"
                >
                    Lupa kata sandi?
                </Link>
            </div>

            <PrimaryButton
                class="mt-3 flex min-h-[42px] w-full justify-center rounded-[11px] bg-[#4f46e5] px-3 py-2.5 text-[11px] font-extrabold uppercase tracking-[0.02em] shadow-[0_4px_12px_rgba(31,28,74,0.12)] transition hover:bg-[#3730a3] focus:bg-[#3730a3] focus:ring-[#a9a5ff] active:bg-[#3730a3]"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
            >
                Masuk
            </PrimaryButton>
        </form>

        <div class="mt-4 text-center text-[11px] text-[#7b8498]">
            Belum punya akun?
            <Link
                :href="route('register')"
                class="font-extrabold text-[#4f46e5] hover:text-[#3730a3] focus:outline-none focus:ring-2 focus:ring-[#a9a5ff] focus:ring-offset-2"
            >
                Daftar toko baru
            </Link>
        </div>
    </GuestLayout>
</template>
