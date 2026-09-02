<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '', remember: false });

const submit = () => form.post(route('admin.login.store'), {
    onFinish: () => form.reset('password'),
});
</script>

<template>
    <GuestLayout>
        <Head title="Admin Login" />
        <h1 class="mb-6 text-xl font-semibold text-gray-900">Super Admin</h1>
        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />
                <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required autofocus autocomplete="username" />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>
            <div class="mt-4">
                <InputLabel for="password" value="Password" />
                <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" required autocomplete="current-password" />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>
            <div class="mt-4 flex justify-end">
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Masuk
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
