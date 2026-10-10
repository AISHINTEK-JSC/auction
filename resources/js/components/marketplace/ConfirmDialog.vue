<script setup lang="ts">
import { CircleHelp, TriangleAlert } from '@lucide/vue';
import {
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogOverlay,
    AlertDialogPortal,
    AlertDialogRoot,
    AlertDialogTitle,
} from 'reka-ui';
import { Button } from '@/components/ui/button';
import { answerConfirm, confirmState } from '@/composables/useConfirm';

/**
 * The page's one confirmation window, opened with confirm() from
 * '@/composables/useConfirm'. Mounted once, by the layout.
 *
 * An alert dialog rather than a plain one: focus starts on "cancel", and a
 * click outside does not dismiss it, so nothing is confirmed or lost by accident.
 */
</script>

<template>
    <AlertDialogRoot
        :open="confirmState.open"
        @update:open="(open) => open || answerConfirm(false)"
    >
        <AlertDialogPortal>
            <AlertDialogOverlay
                class="fixed inset-0 z-50 bg-black/80 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0"
            />
            <AlertDialogContent
                class="fixed top-[50%] left-[50%] z-50 grid w-full max-w-[calc(100%-2rem)] translate-x-[-50%] translate-y-[-50%] gap-5 rounded-lg border bg-background p-6 shadow-lg duration-200 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 sm:max-w-md"
            >
                <div class="flex gap-4">
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-full"
                        :class="
                            confirmState.destructive
                                ? 'bg-destructive/10 text-destructive'
                                : 'bg-muted text-foreground'
                        "
                    >
                        <TriangleAlert
                            v-if="confirmState.destructive"
                            class="size-5"
                        />
                        <CircleHelp v-else class="size-5" />
                    </span>
                    <div class="grid gap-1.5">
                        <AlertDialogTitle
                            class="text-lg leading-snug font-semibold"
                        >
                            {{ confirmState.title }}
                        </AlertDialogTitle>
                        <AlertDialogDescription
                            class="text-sm text-muted-foreground"
                        >
                            {{ confirmState.description }}
                        </AlertDialogDescription>
                    </div>
                </div>

                <div
                    class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                >
                    <AlertDialogCancel as-child>
                        <Button variant="outline">{{
                            confirmState.cancelLabel
                        }}</Button>
                    </AlertDialogCancel>
                    <Button
                        :variant="
                            confirmState.destructive ? 'destructive' : 'default'
                        "
                        data-test="confirm-button"
                        @click="answerConfirm(true)"
                    >
                        {{ confirmState.confirmLabel }}
                    </Button>
                </div>
            </AlertDialogContent>
        </AlertDialogPortal>
    </AlertDialogRoot>
</template>
