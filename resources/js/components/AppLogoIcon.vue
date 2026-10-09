<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

/**
 * The Chốt Giá mark: a price tag with a tick, "the price is settled".
 * The same drawing is public/favicon.svg; keep the two in step.
 */
defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 32 32"
        role="img"
        aria-label="Chốt Giá"
        :class="className"
        v-bind="$attrs"
    >
        <rect width="32" height="32" rx="7" fill="#059669" />
        <path
            fill="#fff"
            d="M7 9a2 2 0 0 1 2-2h6.34a2 2 0 0 1 1.42.59l8.65 8.65a2 2 0 0 1 0 2.83l-6.34 6.34a2 2 0 0 1-2.83 0L7.59 16.76A2 2 0 0 1 7 15.34z"
        />
        <circle cx="11.3" cy="11.3" r="1.6" fill="#059669" />
        <path
            fill="none"
            stroke="#059669"
            stroke-width="2.3"
            stroke-linecap="round"
            stroke-linejoin="round"
            d="m13.5 17.4 2.5 2.5 4.6-5.3"
        />
    </svg>
</template>
