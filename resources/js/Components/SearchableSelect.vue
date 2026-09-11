<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';

interface Option {
    id: number | string;
    name: string;
}

const props = withDefaults(defineProps<{
    id: string;
    label: string;
    modelValue: number | string | null;
    options: Option[];
    placeholder?: string;
    emptyLabel?: string;
    emptyValue?: number | string | null;
    disabled?: boolean;
    allowAdd?: boolean;
}>(), {
    placeholder: 'Cari atau pilih',
    emptyLabel: '',
    emptyValue: '',
    disabled: false,
    allowAdd: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: number | string | null];
    add: [term: string];
}>();

const input = ref<HTMLInputElement | null>(null);
const open = ref(false);
const searchTerm = ref('');
const activeIndex = ref(-1);

const selected = computed(() => props.options.find((option) => String(option.id) === String(props.modelValue)));
const filteredOptions = computed(() => {
    const query = searchTerm.value.trim().toLocaleLowerCase();

    return props.options.filter((option) => option.name.toLocaleLowerCase().includes(query));
});
const emptyOffset = computed(() => (props.emptyLabel ? 1 : 0));
const optionCount = computed(() => filteredOptions.value.length + emptyOffset.value);

watch([selected, () => props.modelValue], () => {
    if (!open.value) {
        searchTerm.value = selected.value?.name ?? '';
    }
}, { immediate: true });

function openMenu(): void {
    if (props.disabled) {
        return;
    }

    open.value = true;
    searchTerm.value = '';
    activeIndex.value = -1;
}

function closeMenu(): void {
    open.value = false;
    activeIndex.value = -1;
    searchTerm.value = selected.value?.name ?? '';
}

function select(value: number | string | null): void {
    emit('update:modelValue', value);
    closeMenu();
}

function add(): void {
    const term = searchTerm.value.trim();
    if (term && props.allowAdd) {
        emit('add', term);
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        event.preventDefault();
        closeMenu();
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (!open.value) {
            openMenu();
        }
        activeIndex.value = Math.min(activeIndex.value + 1, optionCount.value - 1);
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value = Math.max(activeIndex.value - 1, 0);
        return;
    }

    if (event.key === 'Enter' && open.value) {
        event.preventDefault();
        if (activeIndex.value >= emptyOffset.value && activeIndex.value < optionCount.value) {
            select(filteredOptions.value[activeIndex.value - emptyOffset.value].id);
        } else if (props.emptyLabel && activeIndex.value === 0) {
            select(props.emptyValue);
        } else if (!filteredOptions.value.length) {
            add();
        }
    }
}

function onInput(): void {
    if (!open.value) {
        open.value = true;
    }
    activeIndex.value = -1;
}

function onAddMouseDown(event: MouseEvent): void {
    event.preventDefault();
    add();
}

void nextTick(() => {
    if (!open.value) {
        searchTerm.value = selected.value?.name ?? '';
    }
});
</script>

<template>
    <div class="relative">
        <label :for="id" class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
            {{ label }}
        </label>
        <input
            :id="id"
            ref="input"
            v-model="searchTerm"
            type="text"
            role="combobox"
            :placeholder="placeholder"
            :disabled="disabled"
            :aria-expanded="open"
            :aria-controls="`${id}-options`"
            aria-autocomplete="list"
            class="block w-full rounded-xl border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 placeholder:text-slate-300 focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-slate-50"
            @focus="openMenu"
            @input="onInput"
            @keydown="onKeydown"
        />

        <div
            v-if="open"
            :id="`${id}-options`"
            role="listbox"
            class="absolute inset-x-0 top-full z-20 mt-1 max-h-56 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-lg"
        >
            <button
                v-if="emptyLabel"
                type="button"
                role="option"
                :aria-selected="props.modelValue === emptyValue"
                :class="activeIndex === 0 ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-50'"
                class="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                @mousedown.prevent
                @click="select(emptyValue)"
            >
                {{ emptyLabel }}
            </button>
            <button
                v-for="(option, index) in filteredOptions"
                :id="`${id}-option-${option.id}`"
                :key="option.id"
                type="button"
                role="option"
                :aria-selected="String(props.modelValue) === String(option.id)"
                :class="activeIndex === index + (emptyLabel ? 1 : 0) ? 'bg-primary-50 text-primary-700' : 'text-slate-700 hover:bg-slate-50'"
                class="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                @mousedown.prevent
                @click="select(option.id)"
            >
                {{ option.name }}
            </button>
            <button
                v-if="!filteredOptions.length && searchTerm.trim() && allowAdd"
                type="button"
                class="block w-full rounded-lg border border-dashed border-primary-200 px-3 py-2.5 text-left text-sm font-bold text-primary-700 hover:bg-primary-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                @mousedown="onAddMouseDown"
                @click="closeMenu"
            >
                + Tambah “{{ searchTerm.trim() }}”
            </button>
            <p v-else-if="!filteredOptions.length" class="px-3 py-2.5 text-sm text-slate-500">Tidak ada pilihan</p>
        </div>
    </div>
</template>
