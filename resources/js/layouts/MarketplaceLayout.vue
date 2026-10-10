<script setup lang="ts">
import ConfirmDialog from '@/components/marketplace/ConfirmDialog.vue';
import SiteFooter from '@/components/marketplace/SiteFooter.vue';
import SiteHeader from '@/components/marketplace/SiteHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

// Settings pages still pass breadcrumbs through their layout options; the
// marketplace header has no use for them.
defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();
</script>

<template>
    <div class="flex min-h-screen flex-col bg-muted/30">
        <SiteHeader />
        <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6">
            <slot />
        </main>
        <SiteFooter />
        <Toaster />
        <ConfirmDialog />
    </div>
</template>
