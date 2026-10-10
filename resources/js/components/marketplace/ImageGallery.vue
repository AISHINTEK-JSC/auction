<script setup lang="ts">
import { ChevronLeft, ChevronRight, Expand, ImageOff, X } from '@lucide/vue';
import { nextTick, ref, watch } from 'vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * Product photos: a strip of thumbnails beside one large photo. Swipe or use
 * the arrows to move on, pick from the strip, and click the photo (or the
 * expand button) to see it nearly full screen.
 *
 * On a phone the strip lies under the photo and scrolls sideways.
 *
 * Slots: `badge` sits over the photo's top-left corner, `actions` in its
 * top-right corner, after the expand button.
 */
const props = defineProps<{
    /** `url` is the full picture; `thumb` a small version for the strip. */
    images: { url: string; thumb: string }[];
    alt: string;
}>();

const track = ref<HTMLElement>();
const strip = ref<HTMLElement>();
const active = ref(0);
const zoomed = ref(false);

function reducedMotion(): boolean {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function goTo(index: number, smooth = true) {
    const count = props.images.length;

    if (count === 0) {
        return;
    }

    active.value = (index + count) % count;

    track.value?.scrollTo({
        left: active.value * track.value.clientWidth,
        behavior: smooth && !reducedMotion() ? 'smooth' : 'auto',
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

// Keep the current thumbnail in sight. The strip is scrolled by hand:
// scrollIntoView would move the whole page as well.
watch(active, async (index) => {
    await nextTick();

    const list = strip.value;
    const thumb = list?.children[index] as HTMLElement | undefined;

    if (!list || !thumb) {
        return;
    }

    list.scrollTo({
        top: thumb.offsetTop - (list.clientHeight - thumb.offsetHeight) / 2,
        left: thumb.offsetLeft - (list.clientWidth - thumb.offsetWidth) / 2,
        behavior: reducedMotion() ? 'auto' : 'smooth',
    });
});

// In the enlarged view there is no scrolling track, so swipes are read by hand.
let touchStartX = 0;

function onTouchEnd(event: TouchEvent) {
    const delta = event.changedTouches[0].clientX - touchStartX;

    if (Math.abs(delta) > 50) {
        goTo(active.value + (delta < 0 ? 1 : -1), false);
    }
}

// Round white buttons that sit on the photo itself.
const overlayButton =
    'flex items-center justify-center rounded-full bg-white text-neutral-900 shadow-md ring-1 ring-black/5 transition hover:bg-neutral-100 focus-visible:ring-[3px] focus-visible:ring-neutral-900/40 focus-visible:outline-none';
const arrowClass = `${overlayButton} absolute top-1/2 size-10 -translate-y-1/2 sm:size-11`;
const zoomArrowClass =
    'absolute top-1/2 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-black/55 text-white hover:bg-black/75 focus-visible:ring-[3px] focus-visible:ring-white/60 focus-visible:outline-none';
</script>

<template>
    <section>
        <div
            v-if="!images.length"
            class="flex aspect-[4/3] items-center justify-center rounded-2xl bg-muted"
        >
            <ImageOff class="size-10 text-muted-foreground" />
        </div>

        <div
            v-else
            class="flex flex-col-reverse gap-2 rounded-2xl bg-muted p-2 sm:flex-row sm:gap-3 sm:p-3"
        >
            <!-- Thumbnails: a column beside the photo, as tall as the photo;
                 a row under it on a phone. -->
            <div
                v-if="images.length > 1"
                class="relative shrink-0 sm:w-20 lg:w-24"
            >
                <div
                    ref="strip"
                    class="relative flex scrollbar-none gap-2 overflow-auto sm:absolute sm:inset-0 sm:flex-col [&::-webkit-scrollbar]:hidden"
                >
                    <button
                        v-for="(image, index) in images"
                        :key="image.url"
                        type="button"
                        class="aspect-square w-16 shrink-0 overflow-hidden rounded-xl border-2 bg-background p-1 transition-colors sm:w-full"
                        :class="
                            index === active
                                ? 'border-foreground'
                                : 'border-transparent hover:border-foreground/30'
                        "
                        :aria-label="`Xem ảnh ${index + 1}`"
                        :aria-current="index === active"
                        @click="goTo(index)"
                    >
                        <img
                            :src="image.thumb"
                            alt=""
                            loading="lazy"
                            class="size-full rounded-lg object-cover"
                        />
                    </button>
                </div>
            </div>

            <div
                class="relative min-w-0 flex-1 overflow-hidden rounded-xl bg-background"
                role="group"
                aria-roledescription="carousel"
                aria-label="Hình ảnh sản phẩm"
            >
                <div
                    ref="track"
                    class="flex aspect-4/3 snap-x snap-mandatory scrollbar-none overflow-x-auto [&::-webkit-scrollbar]:hidden"
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

                <div class="absolute top-3 left-3 flex flex-wrap gap-2">
                    <slot name="badge" />
                </div>

                <div class="absolute top-3 right-3 flex items-center gap-2">
                    <button
                        type="button"
                        :class="[overlayButton, 'size-10 sm:size-11']"
                        aria-label="Phóng to ảnh"
                        @click="zoomed = true"
                    >
                        <Expand class="size-5" />
                    </button>
                    <slot name="actions" />
                </div>

                <template v-if="images.length > 1">
                    <button
                        type="button"
                        :class="[arrowClass, 'left-3']"
                        aria-label="Ảnh trước"
                        @click="goTo(active - 1)"
                    >
                        <ChevronLeft class="size-5" />
                    </button>
                    <button
                        type="button"
                        :class="[arrowClass, 'right-3']"
                        aria-label="Ảnh sau"
                        @click="goTo(active + 1)"
                    >
                        <ChevronRight class="size-5" />
                    </button>

                    <span
                        class="pointer-events-none absolute right-3 bottom-3 rounded-full bg-black/55 px-2.5 py-1 text-xs text-white tabular-nums"
                    >
                        {{ active + 1 }} / {{ images.length }}
                    </span>
                </template>
            </div>
        </div>

        <!-- Enlarged view -->
        <Dialog v-if="images.length" v-model:open="zoomed">
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
                        :class="[zoomArrowClass, 'left-3']"
                        aria-label="Ảnh trước"
                        @click="goTo(active - 1, false)"
                    >
                        <ChevronLeft class="size-6" />
                    </button>
                    <button
                        type="button"
                        :class="[zoomArrowClass, 'right-3']"
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
