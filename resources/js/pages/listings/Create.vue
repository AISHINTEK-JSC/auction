<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ImagePlus, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import MoneyInput from '@/components/marketplace/MoneyInput.vue';
import RichTextEditor from '@/components/marketplace/RichTextEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/listings';
import { AuctionType } from '@/types';

defineProps<{
    conditions: { value: string; label: string }[];
}>();

const MAX_IMAGES = 8;

const page = usePage();
const categories = computed(() => page.props.categories);
const cities = computed(() => page.props.cities);

const form = useForm({
    title: '',
    description: '',
    category_id: null as number | null,
    city_id: null as number | null,
    condition: 'used',
    images: [] as File[],
    tags: [] as string[],
    auction_type: AuctionType.Up as number,
    start_time: '',
    start_price: '' as number | '',
    end_time: '',
    min_bid_price: '' as number | '',
    auto_extend: true,
    down_minutes: 1 as number | '',
    down_price: '' as number | '',
    finish_price: '' as number | '',
});

const isUp = computed(() => form.auction_type === AuctionType.Up);

// Set by the server when the auction engine refuses the auction as a whole.
const engineError = computed(
    () => (form.errors as Record<string, string | undefined>).engine,
);
const tagInput = ref('');
const previews = ref<string[]>([]);

function addImages(event: Event) {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []).slice(
        0,
        MAX_IMAGES - form.images.length,
    );

    form.images.push(...files);
    previews.value.push(...files.map((file) => URL.createObjectURL(file)));
    input.value = '';
}

function removeImage(index: number) {
    URL.revokeObjectURL(previews.value[index]);
    form.images.splice(index, 1);
    previews.value.splice(index, 1);
}

onBeforeUnmount(() =>
    previews.value.forEach((url) => URL.revokeObjectURL(url)),
);

function addTag() {
    const tag = tagInput.value.trim().replace(/^#/, '');

    if (tag && !form.tags.includes(tag) && form.tags.length < 5) {
        form.tags.push(tag);
    }

    tagInput.value = '';
}

function submit() {
    form.transform((data) => ({
        ...data,
        // Only send what belongs to the chosen auction type.
        ...(isUp.value
            ? { down_minutes: '', down_price: '', finish_price: '' }
            : { end_time: '', min_bid_price: '', auto_extend: false }),
    })).post(store().url, { forceFormData: true });
}

const selectClass =
    'h-9 w-full rounded-md border bg-background px-2 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50';
</script>

<template>
    <Head title="Đăng tin đấu giá" />

    <form class="mx-auto max-w-3xl space-y-6" @submit.prevent="submit">
        <h1 class="text-2xl font-semibold">Đăng tin đấu giá</h1>

        <p
            v-if="engineError"
            class="rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive"
            role="alert"
        >
            {{ engineError }}
        </p>

        <section class="space-y-4 rounded-lg border bg-background p-4">
            <h2 class="font-semibold">Sản phẩm</h2>

            <div class="grid content-start gap-2">
                <Label for="title">Tiêu đề</Label>
                <Input
                    id="title"
                    v-model="form.title"
                    maxlength="150"
                    required
                    placeholder="Ví dụ: iPhone 15 Pro 256GB, còn bảo hành"
                />
                <InputError :message="form.errors.title" />
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="grid content-start gap-2">
                    <Label for="category_id">Danh mục</Label>
                    <select
                        id="category_id"
                        v-model="form.category_id"
                        required
                        :class="selectClass"
                    >
                        <option :value="null" disabled>Chọn danh mục</option>
                        <template
                            v-for="category in categories"
                            :key="category.id"
                        >
                            <option :value="category.id">
                                {{ category.name }}
                            </option>
                            <option
                                v-for="child in category.children"
                                :key="child.id"
                                :value="child.id"
                            >
                                — {{ child.name }}
                            </option>
                        </template>
                    </select>
                    <InputError :message="form.errors.category_id" />
                </div>

                <div class="grid content-start gap-2">
                    <Label for="city_id">Tỉnh / thành phố</Label>
                    <select
                        id="city_id"
                        v-model="form.city_id"
                        required
                        :class="selectClass"
                    >
                        <option :value="null" disabled>Chọn khu vực</option>
                        <option
                            v-for="city in cities"
                            :key="city.id"
                            :value="city.id"
                        >
                            {{ city.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.city_id" />
                </div>

                <div class="grid content-start gap-2">
                    <Label for="condition">Tình trạng</Label>
                    <select
                        id="condition"
                        v-model="form.condition"
                        :class="selectClass"
                    >
                        <option
                            v-for="condition in conditions"
                            :key="condition.value"
                            :value="condition.value"
                        >
                            {{ condition.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.condition" />
                </div>
            </div>

            <div class="grid content-start gap-2">
                <Label>Hình ảnh (tối đa {{ MAX_IMAGES }}, mỗi ảnh ≤ 5MB)</Label>
                <div class="flex flex-wrap gap-2">
                    <div
                        v-for="(preview, index) in previews"
                        :key="preview"
                        class="relative size-24 overflow-hidden rounded-md border"
                    >
                        <img
                            :src="preview"
                            alt=""
                            class="size-full object-cover"
                        />
                        <button
                            type="button"
                            class="absolute top-1 right-1 rounded-full bg-black/60 p-0.5 text-white"
                            :aria-label="`Bỏ ảnh ${index + 1}`"
                            @click="removeImage(index)"
                        >
                            <X class="size-3.5" />
                        </button>
                    </div>
                    <label
                        v-if="form.images.length < MAX_IMAGES"
                        class="flex size-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-md border border-dashed text-xs text-muted-foreground hover:bg-accent"
                    >
                        <ImagePlus class="size-5" />
                        Thêm ảnh
                        <input
                            type="file"
                            accept="image/*"
                            multiple
                            class="sr-only"
                            @change="addImages"
                        />
                    </label>
                </div>
                <InputError
                    :message="
                        form.errors.images ??
                        Object.entries(form.errors).find(([key]) =>
                            key.startsWith('images.'),
                        )?.[1]
                    "
                />
            </div>

            <div class="grid content-start gap-2">
                <Label id="description-label">Mô tả</Label>
                <RichTextEditor
                    v-model="form.description"
                    labelledby="description-label"
                />
                <InputError :message="form.errors.description" />
            </div>

            <div class="grid content-start gap-2">
                <Label for="tag-input">Thẻ (tối đa 5)</Label>
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        v-for="(tag, index) in form.tags"
                        :key="tag"
                        class="flex items-center gap-1 rounded-full bg-secondary px-2 py-0.5 text-xs"
                    >
                        #{{ tag }}
                        <button
                            type="button"
                            :aria-label="`Bỏ thẻ ${tag}`"
                            @click="form.tags.splice(index, 1)"
                        >
                            <X class="size-3" />
                        </button>
                    </span>
                    <Input
                        id="tag-input"
                        v-model="tagInput"
                        maxlength="30"
                        class="w-48"
                        placeholder="Nhập rồi nhấn Enter"
                        @keydown.enter.prevent="addTag"
                        @blur="addTag"
                    />
                </div>
                <InputError :message="form.errors.tags" />
            </div>
        </section>

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
