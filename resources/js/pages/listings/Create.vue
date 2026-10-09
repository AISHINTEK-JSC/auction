<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Banknote, CalendarClock } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import MoneyInput from '@/components/marketplace/MoneyInput.vue';
import ProductFields from '@/components/marketplace/ProductFields.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatVnd } from '@/lib/format';
import { store } from '@/routes/listings';
import { AuctionType } from '@/types';
import type { EditableListing } from '@/types';

/** A finished listing being put up again: everything but the times is carried over. */
type Source = EditableListing & {
    auction_type: number;
    start_price: number;
    min_bid_price: number | null;
    bid_step: number | null;
    auto_extend: boolean;
    down_minutes: number | null;
    down_price: number | null;
    finish_price: number | null;
};

const props = defineProps<{
    conditions: { value: string; label: string }[];
    source: Source | null;
}>();

const source = props.source;

const form = useForm({
    relist_from: source?.id ?? null,
    title: source?.title ?? '',
    description: source?.description ?? '',
    category_id: source?.category_id ?? null,
    city_id: source?.city_id ?? null,
    condition: source?.condition ?? 'used',
    images: [] as File[],
    tags: [...(source?.tags ?? [])],
    auction_type: source?.auction_type ?? (AuctionType.Up as number),
    start_time: '',
    start_price: (source?.start_price ?? '') as number | '',
    end_time: '',
    min_bid_price: (source?.min_bid_price ?? '') as number | '',
    bid_step: (source?.bid_step ?? '') as number | '',
    auto_extend: source?.auto_extend ?? true,
    down_minutes: (source?.down_minutes ?? 1) as number | '',
    down_price: (source?.down_price ?? '') as number | '',
    finish_price: (source?.finish_price ?? '') as number | '',
});

const isUp = computed(() => form.auction_type === AuctionType.Up);
const heading = source ? 'Đăng lại tin đấu giá' : 'Đăng tin đấu giá';

// Set by the server when the auction engine refuses the auction as a whole.
const engineError = computed(() => {
    const errors = form.errors as Record<string, string | undefined>;

    return errors.engine ?? errors.relist_from;
});

const durationPresets = [
    { label: '1 giờ', minutes: 60 },
    { label: '6 giờ', minutes: 360 },
    { label: '1 ngày', minutes: 1440 },
    { label: '3 ngày', minutes: 4320 },
    { label: '7 ngày', minutes: 10080 },
];

/** "YYYY-MM-DDTHH:mm" in local time, the format of a datetime-local input. */
function toLocalInput(date: Date): string {
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** Set the end relative to the chosen start, or to now when it starts at once. */
function setDuration(minutes: number) {
    const start = form.start_time ? new Date(form.start_time) : new Date();

    form.end_time = toLocalInput(new Date(start.getTime() + minutes * 60_000));
}

/** Whole minutes between start (or now) and end; null until an end is chosen. */
const durationMinutes = computed(() => {
    if (!form.end_time) {
        return null;
    }

    const start = form.start_time ? new Date(form.start_time) : new Date();

    return Math.round(
        (new Date(form.end_time).getTime() - start.getTime()) / 60_000,
    );
});

function humanize(minutes: number): string {
    const parts = [
        [Math.floor(minutes / 1440), 'ngày'],
        [Math.floor((minutes % 1440) / 60), 'giờ'],
        [minutes % 60, 'phút'],
    ] as const;

    return (
        parts
            .filter(([value]) => value > 0)
            .map(([value, unit]) => `${value} ${unit}`)
            .join(' ') || '0 phút'
    );
}

const timingSummary = computed(() => {
    if (!isUp.value || durationMinutes.value === null) {
        return '';
    }

    if (durationMinutes.value < 5) {
        return 'Thời gian kết thúc phải sau lúc bắt đầu ít nhất 5 phút.';
    }

    return `Phiên kéo dài ${humanize(durationMinutes.value)}${form.start_time ? '' : ' kể từ lúc đăng'}.`;
});

// For a descending auction: how the price falls and how long that takes.
const priceSummary = computed(() => {
    if (
        isUp.value ||
        !form.start_price ||
        !form.down_price ||
        !form.down_minutes
    ) {
        return '';
    }

    const floor = form.finish_price || 0;

    if (form.down_price >= form.start_price || floor >= form.start_price) {
        return '';
    }

    const drops = Math.ceil((form.start_price - floor) / form.down_price);
    const lowest = floor ? `mức thấp nhất ${formatVnd(floor)}` : 'gần 0 đ';

    return `Giá giảm ${formatVnd(form.down_price)} mỗi ${form.down_minutes} phút, chạm ${lowest} sau ${drops} lần giảm (khoảng ${humanize(drops * form.down_minutes)}). Người mua đầu tiên chấp nhận giá sẽ thắng.`;
});

function submit() {
    form.transform((data) => ({
        ...data,
        // Only send what belongs to the chosen auction type.
        ...(isUp.value
            ? { down_minutes: '', down_price: '', finish_price: '' }
            : {
                  end_time: '',
                  min_bid_price: '',
                  bid_step: '',
                  auto_extend: false,
              }),
    })).post(store().url, { forceFormData: true });
}
</script>

<template>
    <Head :title="heading" />

    <form class="mx-auto max-w-3xl space-y-6" @submit.prevent="submit">
        <h1 class="text-2xl font-semibold">{{ heading }}</h1>

        <p v-if="source" class="text-sm text-muted-foreground">
            Nội dung và hình ảnh được lấy từ tin cũ. Hãy đặt lại giá và thời
            gian cho phiên mới.
        </p>

        <p
            v-if="engineError"
            class="rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive"
            role="alert"
        >
            {{ engineError }}
        </p>

        <ProductFields
            :form="form"
            :errors="form.errors"
            :conditions="conditions"
            :existing-images="source?.images"
        />

        <section class="space-y-5 rounded-lg border bg-background p-4">
            <h2 class="font-semibold">Phiên đấu giá</h2>

            <fieldset class="grid gap-2 sm:grid-cols-2">
                <legend class="sr-only">Loại đấu giá</legend>
                <label
                    v-for="option in [
                        {
                            value: AuctionType.Up,
                            title: 'Đấu giá xuôi',
                            text: 'Giá tăng dần, người đặt cao nhất khi hết giờ sẽ thắng.',
                        },
                        {
                            value: AuctionType.Down,
                            title: 'Đấu giá ngược',
                            text: 'Giá giảm dần theo chu kỳ, người mua đầu tiên sẽ thắng.',
                        },
                    ]"
                    :key="option.value"
                    class="flex cursor-pointer gap-3 rounded-md border p-3"
                    :class="{
                        'border-primary ring-1 ring-primary':
                            form.auction_type === option.value,
                    }"
                >
                    <input
                        v-model="form.auction_type"
                        type="radio"
                        name="auction_type"
                        :value="option.value"
                        class="mt-1"
                    />
                    <span>
                        <span class="block text-sm font-medium">{{
                            option.title
                        }}</span>
                        <span class="text-xs text-muted-foreground">{{
                            option.text
                        }}</span>
                    </span>
                </label>
            </fieldset>
            <InputError :message="form.errors.auction_type" />

            <!-- When -->
            <div class="space-y-3">
                <h3
                    class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                >
                    <CalendarClock class="size-4" />
                    Thời gian
                </h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid content-start gap-2">
                        <Label for="start_time">Bắt đầu</Label>
                        <Input
                            id="start_time"
                            v-model="form.start_time"
                            type="datetime-local"
                        />
                        <p class="text-xs text-muted-foreground">
                            Để trống để bắt đầu ngay.
                        </p>
                        <InputError :message="form.errors.start_time" />
                    </div>

                    <div v-if="isUp" class="grid content-start gap-2">
                        <Label for="end_time">Kết thúc</Label>
                        <Input
                            id="end_time"
                            v-model="form.end_time"
                            type="datetime-local"
                            required
                        />
                        <p class="text-xs text-muted-foreground">
                            Sau lúc bắt đầu ít nhất 5 phút.
                        </p>
                        <InputError :message="form.errors.end_time" />
                    </div>

                    <div v-else class="grid content-start gap-2">
                        <Label for="down_minutes">Chu kỳ giảm giá (phút)</Label>
                        <Input
                            id="down_minutes"
                            v-model.number="form.down_minutes"
                            type="number"
                            min="1"
                            max="1440"
                            required
                        />
                        <p class="text-xs text-muted-foreground">
                            Mỗi chu kỳ giá giảm một lần.
                        </p>
                        <InputError :message="form.errors.down_minutes" />
                    </div>
                </div>

                <div
                    v-if="isUp"
                    class="flex flex-wrap items-center gap-2 text-sm"
                >
                    <span class="text-muted-foreground">Kéo dài:</span>
                    <button
                        v-for="preset in durationPresets"
                        :key="preset.minutes"
                        type="button"
                        class="rounded-full border px-3 py-1 text-xs hover:bg-accent"
                        :class="{
                            'border-primary bg-primary text-primary-foreground hover:bg-primary':
                                durationMinutes === preset.minutes,
                        }"
                        :aria-pressed="durationMinutes === preset.minutes"
                        @click="setDuration(preset.minutes)"
                    >
                        {{ preset.label }}
                    </button>
                </div>

                <p
                    v-if="timingSummary"
                    class="rounded-md bg-muted/50 px-3 py-2 text-sm"
                    role="status"
                >
                    {{ timingSummary }}
                </p>
            </div>

            <!-- How much -->
            <div class="space-y-3 border-t pt-4">
                <h3
                    class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                >
                    <Banknote class="size-4" />
                    Giá
                </h3>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid content-start gap-2">
                        <Label for="start_price">Giá khởi điểm</Label>
                        <MoneyInput
                            id="start_price"
                            v-model="form.start_price"
                            required
                        />
                        <InputError :message="form.errors.start_price" />
                    </div>

                    <template v-if="isUp">
                        <div class="grid content-start gap-2">
                            <Label for="bid_step">Bước giá (tuỳ chọn)</Label>
                            <MoneyInput id="bid_step" v-model="form.bid_step" />
                            <p class="text-xs text-muted-foreground">
                                Mức tăng tối thiểu mỗi lượt đặt.
                            </p>
                            <InputError :message="form.errors.bid_step" />
                        </div>

                        <div class="grid content-start gap-2">
                            <Label for="min_bid_price"
                                >Giá sàn (tuỳ chọn)</Label
                            >
                            <MoneyInput
                                id="min_bid_price"
                                v-model="form.min_bid_price"
                            />
                            <p class="text-xs text-muted-foreground">
                                Giá thấp nhất bạn chịu bán. Người mua không
                                thấy.
                            </p>
                            <InputError :message="form.errors.min_bid_price" />
                        </div>
                    </template>

                    <template v-else>
                        <div class="grid content-start gap-2">
                            <Label for="down_price">Mỗi lần giảm</Label>
                            <MoneyInput
                                id="down_price"
                                v-model="form.down_price"
                                required
                            />
                            <InputError :message="form.errors.down_price" />
                        </div>

                        <div class="grid content-start gap-2">
                            <Label for="finish_price"
                                >Giá thấp nhất (tuỳ chọn)</Label
                            >
                            <MoneyInput
                                id="finish_price"
                                v-model="form.finish_price"
                            />
                            <p class="text-xs text-muted-foreground">
                                Giá ngừng giảm ở mức này.
                            </p>
                            <InputError :message="form.errors.finish_price" />
                        </div>
                    </template>
                </div>

                <p
                    v-if="priceSummary"
                    class="rounded-md bg-muted/50 px-3 py-2 text-sm"
                    role="status"
                >
                    {{ priceSummary }}
                </p>
            </div>

            <label
                v-if="isUp"
                class="flex cursor-pointer items-start gap-3 rounded-md border p-3 text-sm"
            >
                <input
                    v-model="form.auto_extend"
                    type="checkbox"
                    class="mt-0.5"
                />
                <span>
                    <span class="block font-medium"
                        >Tự động gia hạn 5 phút</span
                    >
                    <span class="text-xs text-muted-foreground">
                        Khi có người đặt giá ở phút chót, phiên được kéo dài để
                        người khác kịp trả giá.
                    </span>
                </span>
            </label>
        </section>

        <div class="flex justify-end">
            <Button type="submit" size="lg" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                Đăng tin
            </Button>
        </div>
    </form>
</template>
