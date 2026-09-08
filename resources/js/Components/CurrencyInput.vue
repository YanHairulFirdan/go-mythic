<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import { formatThousands, parseDigits } from '@/utils/currency';

const model = defineModel<number | null>({ default: null });

const input = ref<HTMLInputElement | null>(null);
const display = ref(formatThousands(model.value));

// Keep the field in sync when the bound value changes from outside (reset, prefill).
// Typing already sets `display` to the same string, so this is a no-op then.
watch(model, (value) => {
    const next = formatThousands(value);
    if (next !== display.value) {
        display.value = next;
    }
});

function onInput(event: Event): void {
    const el = event.target as HTMLInputElement;
    const caret = el.selectionStart ?? el.value.length;
    const digitsBeforeCaret = parseDigits(el.value.slice(0, caret)).length;

    const digits = parseDigits(el.value);
    const numeric = digits === '' ? null : Number(digits);

    model.value = numeric;
    display.value = formatThousands(numeric);

    // Restore the caret to the same digit offset after re-grouping.
    void nextTick(() => {
        const field = input.value;
        if (!field) {
            return;
        }

        let pos = 0;
        let seen = 0;
        while (pos < field.value.length && seen < digitsBeforeCaret) {
            if (/\d/.test(field.value[pos])) {
                seen += 1;
            }
            pos += 1;
        }
        field.setSelectionRange(pos, pos);
    });
}
</script>

<template>
    <input
        ref="input"
        :value="display"
        type="text"
        inputmode="numeric"
        autocomplete="off"
        @input="onInput"
    />
</template>
