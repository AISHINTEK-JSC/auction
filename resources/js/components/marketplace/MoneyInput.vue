<script setup lang="ts">
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { caretPosition, formatMoney, parseMoney } from '@/lib/money';

/**
 * Text input for an amount in đồng. The model is the plain number (or '' when
 * empty); what the user sees is grouped by thousands as they type.
 */
const model = defineModel<number | ''>({ required: true });

defineProps<{
    id?: string;
    required?: boolean;
    placeholder?: string;
}>();

const text = ref(formatMoney(model.value));

// The form was changed from outside (reset, prefill).
watch(model, (value) => {
    if (parseMoney(text.value) !== value) {
        text.value = formatMoney(value);
    }
});

function onInput(event: Event) {
    const input = event.target as HTMLInputElement;
    const caret = input.selectionStart ?? input.value.length;
    const digitsAfterCaret = input.value.slice(caret).replace(/\D/g, '').length;

    const value = parseMoney(input.value);
    const formatted = formatMoney(value);

    model.value = value;
    text.value = formatted;

    if (input.value !== formatted) {
        // Rewrite what was typed (letters dropped, separators regrouped) and let
        // the wrapped Input's own v-model see the corrected text.
        input.value = formatted;
        input.dispatchEvent(new Event('input', { bubbles: true }));

        const position = caretPosition(formatted, digitsAfterCaret);
        input.setSelectionRange(position, position);
    }
}
</script>

<template>
    <div class="relative">
        <Input
            :id="id"
            :model-value="text"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            :required="required"
            :placeholder="placeholder ?? '0'"
            class="pr-8 text-right tabular-nums"
            @input="onInput"
        />
        <span
            class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground"
            aria-hidden="true"
            >đ</span
        >
    </div>
</template>
