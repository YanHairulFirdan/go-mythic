<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Buat kata sandi baru" />

        <div class="mb-4">
            <h1 class="text-[19px] font-extrabold tracking-[-0.03em] text-[#172033]">
                Buat kata sandi baru
            </h1>
            <p class="mt-1 text-[11px] text-[#7b8498]">
                Untuk akun <span class="font-extrabold text-[#172033]">{{ form.email }}</span>
            </p>
        </div>

        <form @submit.prevent="submit">
            <div>
                <label
                    for="password"
                    class="mb-1 block text-[10px] font-extrabold uppercase tracking-[0.06em] text-[#636b7d]"
                >
                    Kata sandi baru
                </label>
                <p class="mb-1 text-[11px] text-[#7b8498]">Minimal 8 karakter</p>
                <TextInput
                    id="password"
                    type="password"
                    :class="[
                        'block w-full rounded-[10px] px-3 py-[11px] text-xs text-[#172033] shadow-none focus:ring-[#4f46e5]',
                        form.errors.password ? 'border-[#d94b5b] focus:border-[#d94b5b]' : 'border-[#e8eaf1] focus:border-[#4f46e5]',
                    ]"
                    v-model="form.password"
                    required
                    autofocus
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div>
                <label
                    for="password_confirmation"
                    class="mb-1 mt-3 block text-[10px] font-extrabold uppercase tracking-[0.06em] text-[#636b7d]"
                >
                    Konfirmasi kata sandi baru
                </label>
                <TextInput
                    id="password_confirmation"
                    type="password"
                    :class="[
                        'block w-full rounded-[10px] px-3 py-[11px] text-xs text-[#172033] shadow-none focus:ring-[#4f46e5]',
                        form.errors.password_confirmation ? 'border-[#d94b5b] focus:border-[#d94b5b]' : 'border-[#e8eaf1] focus:border-[#4f46e5]',
                    ]"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <InputError class="mt-2" :message="form.errors.email" />

            <PrimaryButton
                class="mt-4 flex min-h-[42px] w-full justify-center rounded-[11px] bg-[#4f46e5] px-3 py-2.5 text-[11px] font-extrabold uppercase tracking-[0.02em] shadow-[0_4px_12px_rgba(31,28,74,0.12)] transition hover:bg-[#3730a3] focus:bg-[#3730a3] focus:ring-[#a9a5ff] active:bg-[#3730a3]"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
            >
                Simpan kata sandi
            </PrimaryButton>
        </form>

        <div class="mt-4 text-center text-[11px] text-[#7b8498]">
            <Link
                :href="route('login')"
                class="font-extrabold text-[#4f46e5] hover:text-[#3730a3] focus:outline-none focus:ring-2 focus:ring-[#a9a5ff] focus:ring-offset-2"
            >
                Kembali ke Masuk
            </Link>
        </div>
    </GuestLayout>
</template>
