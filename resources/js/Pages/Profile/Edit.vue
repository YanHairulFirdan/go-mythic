<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ChevronLeft, Palette } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import PrototypeLayout from '@/Layouts/PrototypeLayout.vue';

const props = defineProps({
    mustVerifyEmail: { type: Boolean, default: false },
    status: { type: String, default: '' },
});

const page = usePage();
const user = page.props.auth.user;
const isOwner = user.role === 'owner';
const branding = computed(() => page.props.branding);

const profileForm = useForm({ name: user.name, email: user.email });
const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });
const deleteForm = useForm({ password: '' });

const COLOR_PRESETS = [
    { name: 'indigo', hex: '#4f46e5', label: 'Indigo' },
    { name: 'blue', hex: '#2563eb', label: 'Biru' },
    { name: 'teal', hex: '#0d9488', label: 'Teal' },
    { name: 'emerald', hex: '#059669', label: 'Emerald' },
    { name: 'rose', hex: '#e11d48', label: 'Rose' },
    { name: 'violet', hex: '#7c3aed', label: 'Violet' },
];

const initialBranding = page.props.branding.primary;
const brandingForm = useForm({
    primary_color: initialBranding.custom ? initialBranding.hex : initialBranding.name,
    logo: null,
    remove_logo: false,
});
const colorMode = ref(initialBranding.custom ? 'custom' : 'preset');
const logoInput = ref(null);
const logoPreview = ref(null);

const previewSwatch = computed(() => {
    if (colorMode.value === 'custom') {
        return brandingForm.primary_color;
    }
    const match = COLOR_PRESETS.find((preset) => preset.name === brandingForm.primary_color);
    return match ? match.hex : COLOR_PRESETS[0].hex;
});

const choosePreset = (name) => {
    colorMode.value = 'preset';
    brandingForm.primary_color = name;
};

const enableCustom = () => {
    colorMode.value = 'custom';
    if (!/^#[0-9a-fA-F]{6}$/.test(brandingForm.primary_color)) {
        brandingForm.primary_color = previewSwatch.value;
    }
};

const onLogoChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    brandingForm.logo = file;
    if (logoPreview.value) {
        URL.revokeObjectURL(logoPreview.value);
    }
    logoPreview.value = file ? URL.createObjectURL(file) : null;
    if (file) {
        brandingForm.remove_logo = false;
    }
};

const clearLogoSelection = () => {
    brandingForm.logo = null;
    if (logoInput.value) {
        logoInput.value.value = '';
    }
    if (logoPreview.value) {
        URL.revokeObjectURL(logoPreview.value);
        logoPreview.value = null;
    }
};

const submitBranding = () => {
    brandingForm
        .transform((data) => ({ ...data, _method: 'patch' }))
        .post(route('settings.branding.update'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                clearLogoSelection();
                brandingForm.remove_logo = false;
                // Full reload so the <head> theme <style> and the logo are
                // rebuilt from the saved values everywhere in the shell.
                window.location.reload();
            },
        });
};

const passwordInput = ref(null);
const currentPasswordInput = ref(null);
const deletePasswordInput = ref(null);
const confirmingDeletion = ref(false);

const updateProfile = () => profileForm.patch(route('profile.update'), { preserveScroll: true });

const updatePassword = () => {
    passwordForm.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
        onError: () => {
            if (passwordForm.errors.password) {
                passwordForm.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (passwordForm.errors.current_password) {
                passwordForm.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};

const confirmDeletion = () => {
    confirmingDeletion.value = true;
    nextTick(() => deletePasswordInput.value?.focus());
};

const closeDeletion = () => {
    confirmingDeletion.value = false;
    deleteForm.clearErrors();
    deleteForm.reset();
};

const deleteUser = () => {
    deleteForm.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeDeletion(),
        onError: () => deletePasswordInput.value?.focus(),
        onFinish: () => deleteForm.reset(),
    });
};

const fieldClass = 'block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 placeholder:text-slate-300 focus:border-primary-500 focus:ring-primary-500';
const labelClass = 'mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400';
const primaryBtn = 'flex min-h-12 w-full items-center justify-center rounded-xl bg-primary-600 px-4 py-3 text-sm font-bold text-white shadow-sm shadow-primary-200 transition hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 disabled:opacity-50';
</script>

<template>
    <Head title="Profil" />

    <PrototypeLayout>
        <section class="flex items-center gap-3 pb-5 pt-4">
            <Link
                :href="route('more.index')"
                aria-label="Kembali ke Lainnya"
                class="flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:border-primary-200 hover:text-primary-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
                <ChevronLeft class="size-5" />
            </Link>
            <h1 class="text-xl font-bold tracking-tight">Profil</h1>
        </section>

        <section aria-labelledby="profile-info-title">
            <h2 id="profile-info-title" class="text-sm font-bold">Informasi profil</h2>
            <p class="mt-0.5 text-xs text-slate-500">Perbarui nama dan alamat email akun.</p>

            <form class="mt-3 space-y-4 rounded-2xl border border-slate-200 bg-white p-4" @submit.prevent="updateProfile">
                <div>
                    <label for="name" :class="labelClass">Nama</label>
                    <input id="name" v-model="profileForm.name" type="text" required autocomplete="name" :class="fieldClass" />
                    <p v-if="profileForm.errors.name" class="mt-1.5 text-xs font-semibold text-rose-600">{{ profileForm.errors.name }}</p>
                </div>
                <div>
                    <label for="email" :class="labelClass">Email</label>
                    <input id="email" v-model="profileForm.email" type="email" required autocomplete="username" :class="fieldClass" />
                    <p v-if="profileForm.errors.email" class="mt-1.5 text-xs font-semibold text-rose-600">{{ profileForm.errors.email }}</p>
                </div>

                <div v-if="props.mustVerifyEmail && user.email_verified_at === null" class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                    Email belum diverifikasi.
                    <Link :href="route('verification.send')" method="post" as="button" class="font-bold underline">Kirim ulang tautan verifikasi</Link>
                    <span v-if="props.status === 'verification-link-sent'" class="mt-1 block font-semibold text-emerald-600">Tautan verifikasi baru sudah dikirim.</span>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" :disabled="profileForm.processing" :class="primaryBtn">{{ profileForm.processing ? 'Menyimpan…' : 'Simpan' }}</button>
                    <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                        <p v-if="profileForm.recentlySuccessful" class="shrink-0 text-xs font-semibold text-emerald-600">Tersimpan.</p>
                    </Transition>
                </div>
            </form>
        </section>

        <section v-if="isOwner" class="mt-6" aria-labelledby="branding-title">
            <h2 id="branding-title" class="text-sm font-bold">Tampilan aplikasi</h2>
            <p class="mt-0.5 text-xs text-slate-500">Sesuaikan warna utama dan logo agar sesuai identitas perusahaan.</p>

            <form class="mt-3 space-y-5 rounded-2xl border border-slate-200 bg-white p-4" @submit.prevent="submitBranding">
                <div>
                    <span :class="labelClass">Warna utama</span>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-for="preset in COLOR_PRESETS"
                            :key="preset.name"
                            type="button"
                            :aria-label="preset.label"
                            :aria-pressed="colorMode === 'preset' && brandingForm.primary_color === preset.name"
                            class="size-9 rounded-full ring-offset-2 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            :class="colorMode === 'preset' && brandingForm.primary_color === preset.name ? 'ring-2 ring-slate-800' : 'ring-0'"
                            :style="{ backgroundColor: preset.hex }"
                            @click="choosePreset(preset.name)"
                        />
                        <button
                            type="button"
                            aria-label="Warna kustom"
                            :aria-pressed="colorMode === 'custom'"
                            class="flex size-9 items-center justify-center rounded-full border border-dashed border-slate-300 text-slate-400 ring-offset-2 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            :class="colorMode === 'custom' ? 'ring-2 ring-slate-800' : 'ring-0'"
                            @click="enableCustom"
                        >
                            <Palette class="size-4" />
                        </button>
                    </div>

                    <div v-if="colorMode === 'custom'" class="mt-3 flex items-center gap-3">
                        <input v-model="brandingForm.primary_color" type="color" aria-label="Pilih warna kustom" class="size-10 cursor-pointer rounded-lg border border-slate-200 bg-white p-1" />
                        <input
                            v-model="brandingForm.primary_color"
                            type="text"
                            autocapitalize="none"
                            spellcheck="false"
                            placeholder="#2563eb"
                            class="w-32 rounded-xl border-slate-200 bg-white px-3 py-2 text-sm font-semibold uppercase text-slate-700 focus:border-primary-500 focus:ring-primary-500"
                        />
                    </div>
                    <p v-if="brandingForm.errors.primary_color" class="mt-1.5 text-xs font-semibold text-rose-600">{{ brandingForm.errors.primary_color }}</p>

                    <div class="mt-3 flex items-center gap-2.5 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2.5">
                        <span class="flex size-8 items-center justify-center rounded-lg text-sm font-black text-white" :style="{ backgroundColor: previewSwatch }">✦</span>
                        <span class="text-xs font-semibold text-slate-500">Pratinjau warna tombol &amp; aksen</span>
                    </div>
                </div>

                <div>
                    <span :class="labelClass">Logo perusahaan</span>
                    <div class="flex items-center gap-3">
                        <span class="flex size-14 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                            <img v-if="logoPreview || branding.logoUrl" :src="logoPreview || branding.logoUrl" alt="Logo perusahaan" class="size-full object-contain" />
                            <span v-else class="text-[10px] font-bold uppercase tracking-wider text-slate-300">Kosong</span>
                        </span>
                        <div class="space-y-1.5">
                            <label class="inline-flex cursor-pointer items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50 focus-within:ring-2 focus-within:ring-primary-500">
                                <input ref="logoInput" type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="onLogoChange" />
                                Pilih gambar
                            </label>
                            <p class="text-[11px] text-slate-400">PNG, JPG, atau WebP. Maksimal 512 KB.</p>
                        </div>
                    </div>
                    <p v-if="brandingForm.errors.logo" class="mt-1.5 text-xs font-semibold text-rose-600">{{ brandingForm.errors.logo }}</p>

                    <label v-if="branding.logoUrl && !brandingForm.logo" class="mt-2.5 flex items-center gap-2 text-xs font-semibold text-slate-600">
                        <input v-model="brandingForm.remove_logo" type="checkbox" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                        Hapus logo dan kembali ke default
                    </label>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" :disabled="brandingForm.processing" :class="primaryBtn">{{ brandingForm.processing ? 'Menyimpan…' : 'Simpan tampilan' }}</button>
                    <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                        <p v-if="brandingForm.recentlySuccessful" class="shrink-0 text-xs font-semibold text-emerald-600">Tersimpan.</p>
                    </Transition>
                </div>
            </form>
        </section>

        <section class="mt-6" aria-labelledby="password-title">
            <h2 id="password-title" class="text-sm font-bold">Ubah kata sandi</h2>
            <p class="mt-0.5 text-xs text-slate-500">Gunakan kata sandi panjang dan acak agar akun tetap aman.</p>

            <form class="mt-3 space-y-4 rounded-2xl border border-slate-200 bg-white p-4" @submit.prevent="updatePassword">
                <div>
                    <label for="current_password" :class="labelClass">Kata sandi saat ini</label>
                    <input id="current_password" ref="currentPasswordInput" v-model="passwordForm.current_password" type="password" autocomplete="current-password" :class="fieldClass" />
                    <p v-if="passwordForm.errors.current_password" class="mt-1.5 text-xs font-semibold text-rose-600">{{ passwordForm.errors.current_password }}</p>
                </div>
                <div>
                    <label for="password" :class="labelClass">Kata sandi baru</label>
                    <input id="password" ref="passwordInput" v-model="passwordForm.password" type="password" autocomplete="new-password" :class="fieldClass" />
                    <p v-if="passwordForm.errors.password" class="mt-1.5 text-xs font-semibold text-rose-600">{{ passwordForm.errors.password }}</p>
                </div>
                <div>
                    <label for="password_confirmation" :class="labelClass">Konfirmasi kata sandi</label>
                    <input id="password_confirmation" v-model="passwordForm.password_confirmation" type="password" autocomplete="new-password" :class="fieldClass" />
                    <p v-if="passwordForm.errors.password_confirmation" class="mt-1.5 text-xs font-semibold text-rose-600">{{ passwordForm.errors.password_confirmation }}</p>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" :disabled="passwordForm.processing" :class="primaryBtn">{{ passwordForm.processing ? 'Menyimpan…' : 'Simpan' }}</button>
                    <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                        <p v-if="passwordForm.recentlySuccessful" class="shrink-0 text-xs font-semibold text-emerald-600">Tersimpan.</p>
                    </Transition>
                </div>
            </form>
        </section>

        <section class="mt-6 pb-8" aria-labelledby="delete-title">
            <h2 id="delete-title" class="text-sm font-bold text-rose-600">Hapus akun</h2>
            <p class="mt-0.5 text-xs text-slate-500">Setelah dihapus, seluruh data akun hilang permanen. Simpan dulu data yang masih diperlukan.</p>

            <div class="mt-3 rounded-2xl border border-rose-200 bg-white p-4">
                <button
                    type="button"
                    class="flex min-h-12 w-full items-center justify-center rounded-xl border border-rose-200 bg-white px-4 py-3 text-sm font-semibold text-rose-600 transition hover:bg-rose-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500"
                    @click="confirmDeletion"
                >
                    Hapus akun
                </button>
            </div>
        </section>

        <div v-if="confirmingDeletion" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center" role="presentation" @click.self="closeDeletion">
            <section class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
                <h2 id="delete-modal-title" class="text-lg font-bold text-slate-800">Hapus akun ini?</h2>
                <p class="mt-1 text-xs text-slate-500">Masukkan kata sandi untuk mengonfirmasi penghapusan permanen.</p>
                <form class="mt-4 space-y-4" @submit.prevent="deleteUser">
                    <div>
                        <label for="delete_password" :class="labelClass">Kata sandi</label>
                        <input id="delete_password" ref="deletePasswordInput" v-model="deleteForm.password" type="password" autocomplete="current-password" :class="fieldClass" />
                        <p v-if="deleteForm.errors.password" class="mt-1.5 text-xs font-semibold text-rose-600">{{ deleteForm.errors.password }}</p>
                    </div>
                    <div class="flex gap-3 pt-1">
                        <button type="button" class="flex min-h-11 flex-1 items-center justify-center rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500" @click="closeDeletion">Batal</button>
                        <button type="submit" :disabled="deleteForm.processing" class="flex min-h-11 flex-1 items-center justify-center rounded-xl bg-rose-600 px-4 py-3 text-sm font-bold text-white hover:bg-rose-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 disabled:opacity-50">Hapus akun</button>
                    </div>
                </form>
            </section>
        </div>
    </PrototypeLayout>
</template>
