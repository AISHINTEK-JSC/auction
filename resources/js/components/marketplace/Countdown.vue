<script setup lang="ts">
import { computed } from 'vue';
import { useCountdown } from '@/composables/useCountdown';
import { formatDuration } from '@/lib/format';

const props = defineProps<{
    /** ISO timestamp, or seconds from now. */
    deadline: string | number | null;
    /** Shown once the countdown reaches zero. */
    doneLabel?: string;
}>();

const { seconds } = useCountdown(() => props.deadline);

const text = computed(() => {
    if (seconds.value === null) {
        return '—';
    }

    return seconds.value === 0
        ? (props.doneLabel ?? '00:00:00')
        : formatDuration(seconds.value);
});
</script>

<template>
    <span class="tabular-nums">{{ text }}</span>
</template>
