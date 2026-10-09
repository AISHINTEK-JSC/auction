<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Paginated } from '@/types';

defineProps<{
    page: Paginated<unknown>;
}>();
</script>

<template>
    <nav
        v-if="page.last_page > 1"
        class="flex flex-wrap items-center justify-center gap-1"
        aria-label="Phân trang"
    >
        <template v-for="(link, index) in page.links" :key="index">
            <Link
                v-if="link.url"
                :href="link.url"
                preserve-scroll
                class="rounded-md border px-3 py-1.5 text-sm hover:bg-accent"
                :class="{
                    'border-primary bg-primary text-primary-foreground hover:bg-primary':
                        link.active,
                }"
            >
                <span v-html="link.label" />
            </Link>
            <span
                v-else
                class="rounded-md border px-3 py-1.5 text-sm text-muted-foreground opacity-60"
                v-html="link.label"
            />
        </template>
    </nav>
</template>
