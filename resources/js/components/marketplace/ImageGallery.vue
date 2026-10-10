<script setup lang="ts">
import { ChevronLeft, ChevronRight, ImageOff, X, ZoomIn } from '@lucide/vue';
import { ref } from 'vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * Product photos as a slider: swipe or use the arrows, pick from the
 * thumbnails, and click a photo to see it nearly full screen.
 */
const props = defineProps<{
    /** `url` is the full picture; `thumb` a small version for the strip. */
    images: { url: string; thumb: string }[];
    alt: string;
}>();

const track = ref<HTMLElement>();
const active = ref(0);
const zoomed = ref(false);

function goTo(index: number, smooth = true) {
    const count = props.images.length;

    if (count === 0) {
        return;
    }

    active.value = (index + count) % count;

    const reduceMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;

    track.value?.scrollTo({
        left: active.value * track.value.clientWidth,
        behavior: smooth && !reduceMotion ? 'smooth' : 'auto',
    });
}

// Swiping scrolls the track natively (scroll snap); follow it.
function onScroll() {
    if (track.value && track.value.clientWidth > 0) {
        active.value = Math.round(
            track.value.scrollLeft / track.value.clientWidth,
        );
    }
}

// In the enlarged view there is no scrolling track, so swipes are read by hand.
let touchStartX = 0;

function onTouchEnd(event: TouchEvent) {
    const delta = event.changedTouches[0].clientX - touchStartX;

    if (Math.abs(delta) > 50) {
        goTo(active.value + (delta < 0 ? 1 : -1), false);
    }
}

const arrowClass =
    'absolute top-1/2 flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-black/55 text-white hover:bg-black/75 focus-visible:ring-[3px] focus-visible:ring-white/60 focus-visible:outline-none';
</script>

<template>
    <section class="space-y-3">
        <div
            v-if="!images.length"
            class="flex aspect-[4/3] items-center justify-center rounded-lg border bg-background"
        >
            <ImageOff class="size-10 text-muted-foreground" />
        </div>

        <div
            v-else
            class="group relative overflow-hidden rounded-lg border bg-background"
            role="group"
            aria-roledescription="carousel"
            aria-label="Hình ảnh sản phẩm"
        >
            <div
                ref="track"
                class="flex aspect-[4/3] snap-x snap-mandatory [scrollbar-width:none] overflow-x-auto [&::-webkit-scrollbar]:hidden"
                tabindex="0"
                @scroll.passive="onScroll"
                @keydown.left.prevent="goTo(active - 1)"
                @keydown.right.prevent="goTo(active + 1)"
            >
                <button
                    v-for="(image, index) in images"
                    :key="image.url"
                    type="button"
                    class="size-full shrink-0 cursor-zoom-in snap-center"
                    :aria-label="`Phóng to ảnh ${index + 1} trên ${images.length}`"
                    @click="zoomed = true"
                >
                    <img
                        :src="image.url"
                        :alt="`${alt} (ảnh ${index + 1})`"
                        :loading="index === 0 ? 'eager' : 'lazy'"
                        class="size-full object-contain"
                    />
                </button>
            </div>

            <template v-if="images.length > 1">
                <button
                    type="button"
                    :class="[arrowClass, 'left-2']"
                    aria-label="Ảnh trước"
                    @click="goTo(active - 1)"
                >
                    <ChevronLeft class="size-5" />
                </button>
                <button
                    type="button"
                    :class="[arrowClass, 'right-2']"
                    aria-label="Ảnh sau"
                    @click="goTo(active + 1)"
                >
                    <ChevronRight class="size-5" />
                </button>
            </template>

            <span
                class="pointer-events-none absolute right-2 bottom-2 flex items-center gap-1.5 rounded-full bg-black/55 px-2.5 py-1 text-xs text-white tabular-nums"
            >
                <ZoomIn class="size-3.5" />
                {{ active + 1 }} / {{ images.length }}
            </span>
        </div>

        <div v-if="images.length > 1" class="flex flex-wrap gap-2">
            <button
                v-for="(image, index) in images"
                :key="image.url"
                type="button"
                class="size-16 overflow-hidden rounded-md border-2"
                :class="
                    index === active ? 'border-primary' : 'border-transparent'
                "
                :aria-label="`Xem ảnh ${index + 1}`"
                :aria-current="index === active"
                @click="goTo(index)"
            >
                <img
                    :src="image.thumb"
                    alt=""
                    loading="lazy"
                    class="size-full object-cover"
                />
            </button>
        </div>

        <!-- Enlarged view -->
        <Dialog v-model:open="zoomed">
            <DialogContent
                :show-close-button="false"
                class="flex h-[94dvh] w-[96vw] max-w-[96vw] items-center justify-center gap-0 border-0 bg-black/95 p-0 sm:max-w-[96vw]"
                @keydown.left.prevent="goTo(active - 1, false)"
                @keydown.right.prevent="goTo(active + 1, false)"
                @touchstart.passive="touchStartX = $event.touches[0].clientX"
                @touchend.passive="onTouchEnd"
            >
                <DialogTitle class="sr-only">{{ alt }}</DialogTitle>
                <DialogDescription class="sr-only">
                    Ảnh {{ active + 1 }} trên {{ images.length }}. Dùng phím mũi
                    tên trái, phải để chuyển ảnh, Esc để đóng.
                </DialogDescription>

                <img
                    :src="images[active].url"
                    :alt="`${alt} (ảnh ${active + 1})`"
                    class="size-full object-contain"
                />

                <template v-if="images.length > 1">
                    <button
                        type="button"
                        :class="[arrowClass, 'left-3 size-11']"
                        aria-label="Ảnh trước"
                        @click="goTo(active - 1, false)"
                    >
                        <ChevronLeft class="size-6" />
                    </button>
                    <button
                        type="button"
                        :class="[arrowClass, 'right-3 size-11']"
                        aria-label="Ảnh sau"
                        @click="goTo(active + 1, false)"
                    >
                        <ChevronRight class="size-6" />
                    </button>
                </template>

                <span
                    class="absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full bg-black/55 px-3 py-1 text-sm text-white tabular-nums"
                >
                    {{ active + 1 }} / {{ images.length }}
                </span>

                <DialogClose
                    class="absolute top-3 right-3 flex size-10 items-center justify-center rounded-full bg-black/55 text-white hover:bg-black/75 focus-visible:ring-[3px] focus-visible:ring-white/60 focus-visible:outline-none"
                    aria-label="Đóng"
                >
                    <X class="size-5" />
                </DialogClose>
            </DialogContent>
        </Dialog>
    </section>
</template>
