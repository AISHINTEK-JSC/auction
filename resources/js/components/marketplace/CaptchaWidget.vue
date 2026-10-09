<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import InputError from '@/components/InputError.vue';

/**
 * The "are you a robot" check (Cloudflare Turnstile). Renders nothing while
 * the captcha is off on the server.
 *
 * Inside a plain <form> the widget adds its own hidden field
 * "cf-turnstile-response"; a form that builds its data in script reads the
 * token from v-model instead. A token is good for one submit only: call
 * reset() after every attempt.
 */
defineProps<{
    error?: string;
}>();

const token = defineModel<string>({ default: '' });

type Turnstile = {
    render: (
        container: HTMLElement,
        options: Record<string, unknown>,
    ) => string;
    reset: (widgetId: string) => void;
    remove: (widgetId: string) => void;
};

const SCRIPT_URL =
    'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

const page = usePage();
const siteKey = computed(() => page.props.captchaSiteKey);
const container = ref<HTMLElement | null>(null);
const failed = ref(false);
let widgetId: string | null = null;

function turnstile(): Turnstile | undefined {
    return (window as unknown as { turnstile?: Turnstile }).turnstile;
}

/** Load Cloudflare's script once per page, however many widgets ask for it. */
function loadScript(): Promise<void> {
    if (turnstile()) {
        return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
        let script = document.querySelector<HTMLScriptElement>(
            `script[src="${SCRIPT_URL}"]`,
        );

        if (!script) {
            script = document.createElement('script');
            script.src = SCRIPT_URL;
            script.async = true;
            document.head.appendChild(script);
        }

        script.addEventListener('load', () => resolve());
        script.addEventListener('error', () => reject(new Error('captcha')));
    });
}

function reset() {
    token.value = '';

    if (widgetId !== null) {
        turnstile()?.reset(widgetId);
    }
}

defineExpose({ reset });

onMounted(async () => {
    if (!siteKey.value || !container.value) {
        return;
    }

    try {
        await loadScript();
    } catch {
        failed.value = true;

        return;
    }

    // The page may have been left while the script was loading.
    if (!container.value) {
        return;
    }

    widgetId =
        turnstile()?.render(container.value, {
            sitekey: siteKey.value,
            language: 'vi',
            theme: document.documentElement.classList.contains('dark')
                ? 'dark'
                : 'light',
            callback: (value: string) => {
                token.value = value;
            },
            'expired-callback': () => {
                token.value = '';
            },
            'error-callback': () => {
                token.value = '';
            },
        }) ?? null;
});

onBeforeUnmount(() => {
    if (widgetId !== null) {
        turnstile()?.remove(widgetId);
        widgetId = null;
    }
});
</script>

<template>
    <div v-if="siteKey" class="grid gap-2">
        <div ref="container" class="min-h-[65px]" />
        <p v-if="failed" class="text-sm text-red-600 dark:text-red-500">
            Không tải được phần xác nhận "không phải robot". Hãy kiểm tra kết
            nối mạng hoặc tắt trình chặn quảng cáo rồi tải lại trang.
        </p>
        <InputError :message="error" />
    </div>
</template>
