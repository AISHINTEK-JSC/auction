<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { Heart, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useAuthUser } from '@/composables/useAuthUser';
import { getInitials } from '@/composables/useInitials';
import { formatDateTime } from '@/lib/format';
import { login } from '@/routes';
import { destroy, like } from '@/routes/comments';
import { store } from '@/routes/listings/comments';
import type { CommentItem } from '@/types';

const props = defineProps<{
    listingId: number;
    comments: CommentItem[];
}>();

const user = useAuthUser();
const replyingTo = ref<number | null>(null);

const form = useForm<{ body: string; parent_id: number | null }>({
    body: '',
    parent_id: null,
});
const replyForm = useForm<{ body: string; parent_id: number | null }>({
    body: '',
    parent_id: null,
});

function submit() {
    form.post(store(props.listingId).url, {
        preserveScroll: true,
        only: ['comments'],
        onSuccess: () => form.reset(),
    });
}

function submitReply(parentId: number) {
    replyForm.parent_id = parentId;
    replyForm.post(store(props.listingId).url, {
        preserveScroll: true,
        only: ['comments'],
        onSuccess: () => {
            replyForm.reset();
            replyingTo.value = null;
        },
    });
}

function toggleLike(comment: CommentItem) {
    if (!user.value) {
        router.visit(login());

        return;
    }

    router.post(
        like(comment.id).url,
        {},
        { preserveScroll: true, only: ['comments'] },
    );
}

function remove(comment: CommentItem) {
    if (!window.confirm('Xoá bình luận này?')) {
        return;
    }

    router.delete(destroy(comment.id).url, {
        preserveScroll: true,
        only: ['comments'],
    });
}
</script>

<template>
    <section class="space-y-4">
        <h2 class="text-lg font-semibold">
            Bình luận ({{
                comments.reduce(
                    (total, comment) =>
                        total + 1 + (comment.replies?.length ?? 0),
                    0,
                )
            }})
        </h2>

        <form v-if="user" class="space-y-2" @submit.prevent="submit">
            <textarea
                v-model="form.body"
                rows="3"
                maxlength="2000"
                required
                placeholder="Hỏi người bán về sản phẩm…"
                class="w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
            <InputError :message="form.errors.body" />
            <Button type="submit" size="sm" :disabled="form.processing">
                Gửi bình luận
            </Button>
        </form>
        <p v-else class="text-sm text-muted-foreground">
            <Link :href="login()" class="underline underline-offset-4"
                >Đăng nhập</Link
            >
            để bình luận.
        </p>

        <ul class="space-y-4">
            <li v-for="comment in comments" :key="comment.id" class="space-y-3">
                <template
                    v-for="item in [comment, ...(comment.replies ?? [])]"
                    :key="item.id"
                >
                    <div
                        class="flex gap-3"
                        :class="{ 'ml-11': item.id !== comment.id }"
                    >
                        <Avatar class="size-8 shrink-0">
                            <AvatarImage
                                v-if="item.user.avatar"
                                :src="item.user.avatar"
                                :alt="item.user.name"
                            />
                            <AvatarFallback>{{
                                getInitials(item.user.name)
                            }}</AvatarFallback>
                        </Avatar>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm">
                                <span class="font-medium">{{
                                    item.user.name
                                }}</span>
                                <span
                                    class="ml-2 text-xs text-muted-foreground"
                                >
                                    {{ formatDateTime(item.created_at) }}
                                </span>
                            </p>
                            <p class="text-sm break-words whitespace-pre-line">
                                {{ item.body }}
                            </p>
                            <div
                                class="mt-1 flex items-center gap-3 text-xs text-muted-foreground"
                            >
                                <button
                                    type="button"
                                    class="flex items-center gap-1 hover:text-foreground"
                                    :class="{ 'text-red-600': item.liked }"
                                    :aria-pressed="item.liked"
                                    @click="toggleLike(item)"
                                >
                                    <Heart
                                        class="size-3.5"
                                        :fill="
                                            item.liked ? 'currentColor' : 'none'
                                        "
                                    />
                                    {{ item.likes }}
                                </button>
                                <button
                                    v-if="user && item.id === comment.id"
                                    type="button"
                                    class="hover:text-foreground"
                                    @click="
                                        replyingTo =
                                            replyingTo === comment.id
                                                ? null
                                                : comment.id
                                    "
                                >
                                    Trả lời
                                </button>
                                <button
                                    v-if="item.can_delete"
                                    type="button"
                                    class="flex items-center gap-1 hover:text-destructive"
                                    @click="remove(item)"
                                >
                                    <Trash2 class="size-3.5" />
                                    Xoá
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <form
                    v-if="replyingTo === comment.id"
                    class="ml-11 space-y-2"
                    @submit.prevent="submitReply(comment.id)"
                >
                    <textarea
                        v-model="replyForm.body"
                        rows="2"
                        maxlength="2000"
                        required
                        placeholder="Viết trả lời…"
                        class="w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError :message="replyForm.errors.body" />
                    <Button
                        type="submit"
                        size="sm"
                        :disabled="replyForm.processing"
                    >
                        Gửi trả lời
                    </Button>
                </form>
            </li>
        </ul>
    </section>
</template>
