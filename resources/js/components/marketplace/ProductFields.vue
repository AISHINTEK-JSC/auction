<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ImagePlus, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import RichTextEditor from '@/components/marketplace/RichTextEditor.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ProductFormFields } from '@/types';

/**
 * The "what is being sold" part shared by the create and edit forms.
 */
const MAX_IMAGES = 8;

/** Ids of already stored images the seller wants to drop (edit form only). */
const removed = defineModel<number[]>('removed', { default: () => [] });

const props = defineProps<{
    /** The parent's Inertia form; its fields are edited in place. */
    form: ProductFormFields;
    errors: Partial<Record<string, string>>;
    conditions: { value: string; label: string }[];
    /** Images the listing already has. */
    existingImages?: { id: number; url: string }[];
    /** Whether stored images can be dropped. Not when relisting: they are copied as they are. */
    removable?: boolean;
}>();

const page = usePage();
const categories = computed(() => page.props.categories);
const cities = computed(() => page.props.cities);

const kept = computed(() =>
    (props.existingImages ?? []).filter(
        (image) => !removed.value.includes(image.id),
    ),
);
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

const selectClass =
    'h-9 w-full rounded-md border bg-background px-2 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50';
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
                    <template v-for="category in categories" :key="category.id">
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
                <InputError :message="errors.category_id" />
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
                <InputError :message="errors.city_id" />
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
                <InputError :message="errors.condition" />
            </div>
        </div>

        <div class="grid content-start gap-2">
            <Label>Hình ảnh (tối đa {{ MAX_IMAGES }}, mỗi ảnh ≤ 5MB)</Label>
            <div class="flex flex-wrap gap-2">
                <div
                    v-for="image in kept"
                    :key="image.id"
                    class="relative size-24 overflow-hidden rounded-md border"
                >
                    <img
                        :src="image.url"
                        alt=""
                        class="size-full object-cover"
                    />
                    <button
                        v-if="removable"
                        type="button"
                        class="absolute top-1 right-1 rounded-full bg-black/60 p-0.5 text-white"
                        aria-label="Bỏ ảnh này"
                        @click="removed.push(image.id)"
                    >
                        <X class="size-3.5" />
                    </button>
                </div>
                <div
                    v-for="(preview, index) in previews"
                    :key="preview"
                    class="relative size-24 overflow-hidden rounded-md border"
                >
                    <img :src="preview" alt="" class="size-full object-cover" />
                    <button
                        type="button"
                        class="absolute top-1 right-1 rounded-full bg-black/60 p-0.5 text-white"
                        :aria-label="`Bỏ ảnh mới ${index + 1}`"
                        @click="removeNew(index)"
                    >
                        <X class="size-3.5" />
                    </button>
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
