<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { privacy, terms } from '@/routes/legal';

defineProps<{
    title: string;
    updatedAt: string;
    current: 'terms' | 'privacy';
}>();
</script>

<template>
    <Head :title="title" />

    <article class="mx-auto max-w-3xl">
        <header class="mb-8 space-y-2 border-b pb-6">
            <h1 class="text-3xl font-semibold tracking-tight">{{ title }}</h1>
            <p class="text-sm text-muted-foreground">
                Cập nhật lần cuối: {{ updatedAt }}
            </p>
            <nav class="flex gap-4 pt-2 text-sm" aria-label="Văn bản pháp lý">
                <Link
                    :href="terms()"
                    class="underline-offset-4 hover:underline"
                    :class="
                        current === 'terms'
                            ? 'font-medium'
                            : 'text-muted-foreground'
                    "
                    >Điều khoản sử dụng</Link
                >
                <Link
                    :href="privacy()"
                    class="underline-offset-4 hover:underline"
                    :class="
                        current === 'privacy'
                            ? 'font-medium'
                            : 'text-muted-foreground'
                    "
                    >Chính sách quyền riêng tư</Link
                >
            </nav>
        </header>

        <div class="legal-text">
            <slot />
        </div>
    </article>
</template>

<style scoped>
.legal-text {
    font-size: 0.9375rem;
    line-height: 1.75;
}

.legal-text :deep(h2) {
    margin-top: 2rem;
    margin-bottom: 0.75rem;
    font-size: 1.25rem;
    font-weight: 600;
    line-height: 1.4;
}

.legal-text :deep(p) {
    margin-bottom: 0.75rem;
}

.legal-text :deep(ul) {
    margin-bottom: 0.75rem;
    padding-left: 1.25rem;
    list-style: disc;
}

.legal-text :deep(li) {
    margin-bottom: 0.375rem;
}

.legal-text :deep(a) {
    text-decoration: underline;
    text-underline-offset: 4px;
}
</style>
