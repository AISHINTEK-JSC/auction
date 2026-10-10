<script setup lang="ts">
import { ChevronLeft, ChevronRight, ImagePlus, Sparkles, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import CategorySelect from '@/components/marketplace/CategorySelect.vue';
import CitySelect from '@/components/marketplace/CitySelect.vue';
import RichTextEditor from '@/components/marketplace/RichTextEditor.vue';
import SearchableSelect from '@/components/marketplace/SearchableSelect.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ProductFormFields } from '@/types';

/**
 * The "what is being sold" part shared by the create and edit forms.
 */
const MAX_IMAGES = 8;

/** Ids of already stored images the seller wants to drop (edit form only). */
const removed = defineModel<number[]>('removed', { default: () => [] });
/** Ids of the stored images that stay, in display order (edit form only). The first is the cover. */
const order = defineModel<number[]>('order', { default: () => [] });

const props = defineProps<{
    /** The parent's Inertia form; its fields are edited in place. */
    form: ProductFormFields;
    errors: Partial<Record<string, string>>;
    conditions: { value: string; label: string }[];
    /** Brands already used on the site, suggested while typing. */
    brands: string[];
    /** Images the listing already has. */
    existingImages?: { id: number; url: string }[];
    /** Whether stored images can be dropped. Not when relisting: they are copied as they are. */
    removable?: boolean;
}>();

// Stored images in the seller's order. Without an order (relisting) they are shown as stored.
const kept = computed(() => {
    const images = (props.existingImages ?? []).filter(
        (image) => !removed.value.includes(image.id),
    );

    if (order.value.length === 0) {
        return images;
    }

    return [...images].sort(
        (a, b) => order.value.indexOf(a.id) - order.value.indexOf(b.id),
    );
});

function removeExisting(id: number) {
    removed.value.push(id);
    order.value = order.value.filter((kept) => kept !== id);
}

/** Swap a stored image with its neighbour. */
function moveExisting(index: number, step: -1 | 1) {
    const ids = kept.value.map((image) => image.id);
    const target = index + step;

    if (target < 0 || target >= ids.length) {
        return;
    }

    [ids[index], ids[target]] = [ids[target], ids[index]];
    order.value = ids;
}

/** Swap a newly added image with its neighbour; new images always follow the stored ones. */
function moveNew(index: number, step: -1 | 1) {
    const target = index + step;

    if (target < 0 || target >= props.form.images.length) {
        return;
    }

    for (const list of [props.form.images, previews.value]) {
        [list[index], list[target]] = [list[target], list[index]] as never[];
    }
}
const room = computed(
    () => MAX_IMAGES - kept.value.length - props.form.images.length,
);

const tagInput = ref('');
const previews = ref<string[]>([]);

function addImages(event: Event) {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []).slice(0, room.value);

    props.form.images.push(...files);
    previews.value.push(...files.map((file) => URL.createObjectURL(file)));
    input.value = '';
}

function removeNew(index: number) {
    URL.revokeObjectURL(previews.value[index]);
    props.form.images.splice(index, 1);
    previews.value.splice(index, 1);
}

onBeforeUnmount(() =>
    previews.value.forEach((url) => URL.revokeObjectURL(url)),
);

function addTag() {
    const tag = tagInput.value.trim().replace(/^#/, '');

    if (tag && !props.form.tags.includes(tag) && props.form.tags.length < 5) {
        props.form.tags.push(tag);
    }

    tagInput.value = '';
}

const imageError = computed(
    () =>
        props.errors.images ??
        Object.entries(props.errors).find(([key]) =>
            key.startsWith('images.'),
        )?.[1],
);
</script>

<template>
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
            <InputError :message="errors.title" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid content-start gap-2">
                <Label for="category_id">Danh mục</Label>
                <CategorySelect
                    id="category_id"
                    v-model="form.category_id"
                    placeholder="Chọn danh mục"
                />
                <InputError :message="errors.category_id" />
            </div>

            <div class="grid content-start gap-2">
                <Label for="city_id">Tỉnh / thành phố</Label>
                <CitySelect
                    id="city_id"
                    v-model="form.city_id"
                    placeholder="Chọn khu vực"
                />
                <InputError :message="errors.city_id" />
            </div>

            <div class="grid content-start gap-2">
                <Label for="condition">Tình trạng</Label>
                <SearchableSelect
                    id="condition"
                    v-model="form.condition"
                    :options="conditions"
                    :icon="Sparkles"
                    placeholder="Chọn tình trạng"
                />
                <InputError :message="errors.condition" />
            </div>

            <div class="grid content-start gap-2">
                <Label for="brand">
                    Thương hiệu
                    <span class="font-normal text-muted-foreground"
                        >(tuỳ chọn)</span
                    >
                </Label>
                <Input
                    id="brand"
                    v-model="form.brand"
                    maxlength="60"
                    list="brand-suggestions"
                    autocomplete="off"
                    placeholder="Ví dụ: Apple, Sony, Seiko"
                />
                <datalist id="brand-suggestions">
                    <option
                        v-for="brand in brands"
                        :key="brand"
                        :value="brand"
                    />
                </datalist>
                <InputError :message="errors.brand" />
            </div>
        </div>

        <div class="grid content-start gap-2">
            <Label>Hình ảnh (tối đa {{ MAX_IMAGES }}, mỗi ảnh ≤ 5MB)</Label>
            <p class="text-xs text-muted-foreground">
                Ảnh đầu tiên là ảnh bìa. Dùng mũi tên trên ảnh để đổi thứ tự.
            </p>
            <div class="flex flex-wrap gap-2">
                <div
                    v-for="(image, index) in kept"
                    :key="image.id"
                    class="relative size-24 overflow-hidden rounded-md border"
                >
                    <img
                        :src="image.url"
                        alt=""
                        class="size-full object-cover"
                    />
                    <span
                        v-if="index === 0"
                        class="absolute top-1 left-1 rounded bg-primary px-1 text-[10px] font-medium text-primary-foreground"
                        >Ảnh bìa</span
                    >
                    <button
                        v-if="removable"
                        type="button"
                        class="absolute top-1 right-1 rounded-full bg-black/60 p-0.5 text-white"
                        :aria-label="`Bỏ ảnh ${index + 1}`"
                        @click="removeExisting(image.id)"
                    >
                        <X class="size-3.5" />
                    </button>
                    <div
                        v-if="removable && kept.length > 1"
                        class="absolute inset-x-0 bottom-0 flex justify-between bg-black/55 text-white"
                    >
                        <button
                            type="button"
                            class="p-0.5 disabled:opacity-30"
                            :disabled="index === 0"
                            :aria-label="`Chuyển ảnh ${index + 1} lên trước`"
                            @click="moveExisting(index, -1)"
                        >
                            <ChevronLeft class="size-4" />
                        </button>
                        <button
                            type="button"
                            class="p-0.5 disabled:opacity-30"
                            :disabled="index === kept.length - 1"
                            :aria-label="`Chuyển ảnh ${index + 1} ra sau`"
                            @click="moveExisting(index, 1)"
                        >
                            <ChevronRight class="size-4" />
                        </button>
                    </div>
                </div>
                <div
                    v-for="(preview, index) in previews"
                    :key="preview"
                    class="relative size-24 overflow-hidden rounded-md border"
                >
                    <img :src="preview" alt="" class="size-full object-cover" />
                    <span
                        v-if="index === 0 && kept.length === 0"
                        class="absolute top-1 left-1 rounded bg-primary px-1 text-[10px] font-medium text-primary-foreground"
                        >Ảnh bìa</span
                    >
                    <button
                        type="button"
                        class="absolute top-1 right-1 rounded-full bg-black/60 p-0.5 text-white"
                        :aria-label="`Bỏ ảnh mới ${index + 1}`"
                        @click="removeNew(index)"
                    >
                        <X class="size-3.5" />
                    </button>
                    <div
                        v-if="previews.length > 1"
                        class="absolute inset-x-0 bottom-0 flex justify-between bg-black/55 text-white"
                    >
                        <button
                            type="button"
                            class="p-0.5 disabled:opacity-30"
                            :disabled="index === 0"
                            :aria-label="`Chuyển ảnh mới ${index + 1} lên trước`"
                            @click="moveNew(index, -1)"
                        >
                            <ChevronLeft class="size-4" />
                        </button>
                        <button
                            type="button"
                            class="p-0.5 disabled:opacity-30"
                            :disabled="index === previews.length - 1"
                            :aria-label="`Chuyển ảnh mới ${index + 1} ra sau`"
                            @click="moveNew(index, 1)"
                        >
                            <ChevronRight class="size-4" />
                        </button>
                    </div>
                </div>
                <label
                    v-if="room > 0"
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
            <InputError :message="imageError" />
        </div>

        <div class="grid content-start gap-2">
            <Label id="description-label">Mô tả</Label>
            <RichTextEditor
                v-model="form.description"
                labelledby="description-label"
            />
            <InputError :message="errors.description" />
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
            <InputError :message="errors.tags" />
        </div>
    </section>
</template>
