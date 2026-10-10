<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import ProductFields from '@/components/marketplace/ProductFields.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { update } from '@/routes/listings';
import type { EditableListing } from '@/types';

const props = defineProps<{
    conditions: { value: string; label: string }[];
    brands: string[];
    listing: EditableListing & { has_auction: boolean };
}>();

const form = useForm({
    // Files cannot be sent with PUT, so the form posts and names the real method.
    _method: 'put',
    title: props.listing.title,
    description: props.listing.description,
    category_id: props.listing.category_id,
    city_id: props.listing.city_id,
    condition: props.listing.condition,
    brand: props.listing.brand,
    images: [] as File[],
    tags: [...props.listing.tags],
    remove_images: [] as number[],
    image_order: props.listing.images.map((image) => image.id),
});

function submit() {
    form.post(update(props.listing.id).url, { forceFormData: true });
}
</script>

<template>
    <Head title="Sửa tin đăng" />

    <form class="mx-auto max-w-3xl space-y-6" @submit.prevent="submit">
        <div>
            <h1 class="text-2xl font-semibold">Sửa tin đăng</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Bạn có thể sửa nội dung và hình ảnh. Giá và thời gian của phiên
                đấu giá không thay đổi được sau khi đăng.
            </p>
        </div>

        <ProductFields
            v-model:removed="form.remove_images"
            v-model:order="form.image_order"
            :form="form"
            :errors="form.errors"
            :conditions="conditions"
            :brands="brands"
            :existing-images="listing.images"
            removable
        />

        <div class="flex justify-end gap-2">
            <Button variant="outline" as-child>
                <Link :href="dashboard()">Huỷ</Link>
            </Button>
            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                Lưu thay đổi
            </Button>
        </div>
    </form>
</template>
