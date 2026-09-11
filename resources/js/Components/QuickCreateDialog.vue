<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import { postJson } from '@/utils/http';

interface Field {
    name: string;
    label: string;
    required?: boolean;
    placeholder?: string;
}

const props = withDefaults(defineProps<{
    open: boolean;
    title: string;
    endpoint: string;
    fields: Field[];
    responseKey: string;
    payload?: Record<string, unknown>;
    initialValues?: Record<string, string>;
    submitLabel?: string;
}>(), {
    payload: () => ({}),
    submitLabel: 'Simpan',
});

const emit = defineEmits<{
    'update:open': [value: boolean];
    created: [record: Record<string, unknown>];
}>();

const values = ref<Record<string, string>>({});
const errors = ref<Record<string, string>>({});
const generalError = ref('');
const processing = ref(false);
const dialogEl = ref<HTMLElement | null>(null);
const opener = ref<HTMLElement | null>(null);

function onEscape(event: KeyboardEvent): void {
    if (event.key === 'Escape' && props.open) {
        event.preventDefault();
        close();
    }
}

watch(() => props.open, (open) => {
    if (open) {
        opener.value = document.activeElement as HTMLElement | null;
        values.value = Object.fromEntries(props.fields.map((field) => [field.name, props.initialValues?.[field.name] ?? '']));
        errors.value = {};
        generalError.value = '';
        document.addEventListener('keydown', onEscape);
        void nextTick(() => dialogEl.value?.querySelector('input')?.focus());
    } else {
        document.removeEventListener('keydown', onEscape);
        void nextTick(() => opener.value?.focus());
    }
}, { immediate: true });

function close(): void {
    emit('update:open', false);
}

async function submit(): Promise<void> {
    processing.value = true;
    errors.value = {};
    generalError.value = '';

    try {
        const { ok, status, data } = await postJson<Record<string, unknown>>(props.endpoint, {
            ...values.value,
            ...props.payload,
        });

        if (ok) {
            emit('created', (data[props.responseKey] ?? {}) as Record<string, unknown>);
            close();
            return;
        }

        if (status === 422 && data.errors) {
            const bag = data.errors as Record<string, string[]>;
            errors.value = Object.fromEntries(
                Object.entries(bag).map(([key, messages]) => [key, messages[0]]),
            );
        } else if (status === 403) {
            generalError.value = 'Anda tidak punya akses untuk menambah data ini.';
        } else {
            generalError.value = 'Gagal menyimpan. Coba lagi.';
        }
    } catch {
        generalError.value = 'Gagal terhubung ke server.';
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center"
        role="presentation"
        @click.self="close"
    >
        <section ref="dialogEl" class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl" role="dialog" aria-modal="true" :aria-label="title">
            <h2 class="text-lg font-bold text-slate-800">{{ title }}</h2>

            <form class="mt-4 space-y-4" @submit.prevent="submit">
                <div v-if="generalError" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700">
                    {{ generalError }}
                </div>

                <div v-for="field in fields" :key="field.name">
                    <label :for="`qc-${field.name}`" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                        {{ field.label }}
                        <span v-if="!field.required" class="font-medium normal-case tracking-normal text-slate-400">(opsional)</span>
                    </label>
                    <input
                        :id="`qc-${field.name}`"
                        v-model="values[field.name]"
                        type="text"
                        :placeholder="field.placeholder"
                        class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 placeholder:text-slate-300 focus:border-primary-500 focus:ring-primary-500"
                    />
                    <p v-if="errors[field.name]" class="mt-1.5 text-xs font-semibold text-rose-600">{{ errors[field.name] }}</p>
                </div>

                <div class="flex gap-3 pt-1">
                    <button type="button" class="flex min-h-11 flex-1 items-center justify-center rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500" @click="close">Batal</button>
                    <button type="submit" :disabled="processing" class="flex min-h-11 flex-1 items-center justify-center rounded-xl bg-primary-600 px-4 py-3 text-sm font-bold text-white hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:opacity-50">{{ processing ? 'Menyimpan…' : submitLabel }}</button>
                </div>
            </form>
        </section>
    </div>
</template>
