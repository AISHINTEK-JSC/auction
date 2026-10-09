import { computed, onBeforeUnmount, onMounted, ref, toValue, watch } from 'vue';
import type { MaybeRefOrGetter } from 'vue';

/**
 * Seconds left until a deadline, ticking once a second.
 *
 * The deadline is either an ISO timestamp or a number of seconds from "now"
 * (the engine's remaining_time); the latter restarts whenever a fresh value arrives.
 */
export function useCountdown(
    deadline: MaybeRefOrGetter<string | number | null | undefined>,
) {
    const now = ref(Date.now());
    const target = ref<number | null>(null);
    let timer: ReturnType<typeof setInterval> | undefined;

    watch(
        () => toValue(deadline),
        (value) => {
            if (value === null || value === undefined) {
                target.value = null;
            } else if (typeof value === 'number') {
                target.value = Date.now() + value * 1000;
            } else {
                target.value = new Date(value).getTime();
            }

            now.value = Date.now();
        },
        { immediate: true },
    );

    onMounted(() => {
        timer = setInterval(() => (now.value = Date.now()), 1000);
    });

    onBeforeUnmount(() => clearInterval(timer));

    const seconds = computed(() =>
        target.value === null
            ? null
            : Math.max(0, Math.round((target.value - now.value) / 1000)),
    );

    return { seconds };
}
