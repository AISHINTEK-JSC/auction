<script setup lang="ts">
import { Star } from '@lucide/vue';

/**
 * Shows a rating out of five. With v-model it becomes an input.
 */
const model = defineModel<number>();

const props = defineProps<{
    /** Read-only value, used when there is no v-model. */
    value?: number;
    size?: 'sm' | 'md';
}>();

const editable = model.value !== undefined;
const filled = (star: number) =>
    star <= Math.round(editable ? (model.value ?? 0) : (props.value ?? 0));
</script>

<template>
    <span
        v-if="!editable"
        class="inline-flex items-center gap-0.5"
        role="img"
        :aria-label="`${value ?? 0} trên 5 sao`"
    >
        <Star
            v-for="star in 5"
            :key="star"
            :class="[
                size === 'md' ? 'size-5' : 'size-3.5',
                filled(star)
                    ? 'fill-amber-400 text-amber-400'
                    : 'text-muted-foreground/40',
            ]"
        />
    </span>
    <span v-else class="inline-flex items-center gap-1" role="radiogroup">
        <button
            v-for="star in 5"
            :key="star"
            type="button"
            role="radio"
            :aria-checked="model === star"
            :aria-label="`${star} sao`"
            class="rounded p-0.5 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            @click="model = star"
        >
            <Star
                class="size-7"
                :class="
                    filled(star)
                        ? 'fill-amber-400 text-amber-400'
                        : 'text-muted-foreground/40'
                "
            />
        </button>
    </span>
</template>
