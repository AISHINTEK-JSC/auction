<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import MoneyInput from '@/components/marketplace/MoneyInput.vue';
import ProductFields from '@/components/marketplace/ProductFields.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/listings';
import { AuctionType } from '@/types';
import type { EditableListing } from '@/types';

/** A finished listing being put up again: everything but the times is carried over. */
type Source = EditableListing & {
    auction_type: number;
    start_price: number;
    min_bid_price: number | null;
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

function submit() {
    form.transform((data) => ({
        ...data,
        // Only send what belongs to the chosen auction type.
        ...(isUp.value
            ? { down_minutes: '', down_price: '', finish_price: '' }
            : { end_time: '', min_bid_price: '', auto_extend: false }),
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

        <section class="space-y-4 rounded-lg border bg-background p-4">
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

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid content-start gap-2">
                    <Label for="start_price">Giá khởi điểm</Label>
                    <MoneyInput
                        id="start_price"
                        v-model="form.start_price"
                        required
                    />
                    <InputError :message="form.errors.start_price" />
                </div>

                <div class="grid content-start gap-2">
                    <Label for="start_time">Bắt đầu lúc</Label>
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

                <template v-if="isUp">
                    <div class="grid content-start gap-2">
                        <Label for="end_time">Kết thúc lúc</Label>
                        <Input
                            id="end_time"
                            v-model="form.end_time"
                            type="datetime-local"
                            required
                        />
                        <p class="text-xs text-muted-foreground">
                            Sau thời điểm bắt đầu ít nhất 5 phút.
                        </p>
                        <InputError :message="form.errors.end_time" />
                    </div>

                    <div class="grid content-start gap-2">
                        <Label for="min_bid_price">Giá sàn (tuỳ chọn)</Label>
                        <MoneyInput
                            id="min_bid_price"
                            v-model="form.min_bid_price"
                        />
                        <p class="text-xs text-muted-foreground">
                            Phiên chỉ thành công khi giá cao nhất đạt mức này.
                            Người mua không thấy giá sàn.
                        </p>
                        <InputError :message="form.errors.min_bid_price" />
                    </div>

                    <label class="flex items-start gap-2 text-sm sm:col-span-2">
                        <input
                            v-model="form.auto_extend"
                            type="checkbox"
                            class="mt-1"
                        />
                        <span>
                            Tự động gia hạn 5 phút khi có người đặt giá ở phút
                            chót
                        </span>
                    </label>
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
                        <Label for="down_minutes">Chu kỳ giảm (phút)</Label>
                        <Input
                            id="down_minutes"
                            v-model.number="form.down_minutes"
                            type="number"
                            min="1"
                            max="1440"
                            required
                        />
                        <InputError :message="form.errors.down_minutes" />
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
                            Giá ngừng giảm khi chạm mức này.
                        </p>
                        <InputError :message="form.errors.finish_price" />
                    </div>
                </template>
            </div>
        </section>

        <div class="flex justify-end">
            <Button type="submit" size="lg" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                Đăng tin
            </Button>
        </div>
    </form>
</template>
