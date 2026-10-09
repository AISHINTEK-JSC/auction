<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Gavel } from '@lucide/vue';
import { computed } from 'vue';
import { useAuthUser } from '@/composables/useAuthUser';
import { dashboard, home, login, register } from '@/routes';
import { show as categoryShow } from '@/routes/categories';
import { index as giveawaysIndex } from '@/routes/giveaways';
import { create, index } from '@/routes/listings';
import { index as notificationsIndex } from '@/routes/notifications';
import { edit as profileEdit } from '@/routes/profile';

const page = usePage();
const user = useAuthUser();
const categories = computed(() => page.props.categories ?? []);

const linkClass = 'hover:text-foreground hover:underline underline-offset-4';
</script>

<template>
    <footer class="border-t bg-background">
        <div
            class="mx-auto grid max-w-7xl grid-cols-2 gap-8 px-4 py-10 text-sm lg:grid-cols-4"
        >
            <div class="col-span-2 space-y-3 lg:col-span-1">
                <Link
                    :href="home()"
                    class="flex items-center gap-2 font-semibold"
                >
                    <span
                        class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground"
                    >
                        <Gavel class="size-4" />
                    </span>
                    {{ page.props.name }}
                </Link>
                <p class="text-muted-foreground">
                    Sàn đấu giá trực tuyến: đấu giá xuôi cho người muốn bán được
                    giá, đấu giá ngược cho người muốn bán nhanh.
                </p>
            </div>

            <nav aria-label="Khám phá">
                <h2 class="mb-3 font-semibold">Khám phá</h2>
                <ul class="space-y-2 text-muted-foreground">
                    <li>
                        <Link
                            :href="index({ query: { status: 'running' } })"
                            :class="linkClass"
                            >Đang diễn ra</Link
                        >
                    </li>
                    <li>
                        <Link
                            :href="index({ query: { status: 'upcoming' } })"
                            :class="linkClass"
                            >Sắp diễn ra</Link
                        >
                    </li>
                    <li>
                        <Link
                            :href="index({ query: { status: 'ended' } })"
                            :class="linkClass"
                            >Đã kết thúc</Link
                        >
                    </li>
                    <li>
                        <Link :href="giveawaysIndex()" :class="linkClass"
                            >Quà tặng</Link
                        >
                    </li>
                </ul>
            </nav>

            <nav aria-label="Danh mục">
                <h2 class="mb-3 font-semibold">Danh mục</h2>
                <ul class="space-y-2 text-muted-foreground">
                    <li v-for="category in categories" :key="category.id">
                        <Link
                            :href="categoryShow(category.slug)"
                            :class="linkClass"
                            >{{ category.name }}</Link
                        >
                    </li>
                </ul>
            </nav>

            <nav aria-label="Tài khoản">
                <h2 class="mb-3 font-semibold">Tài khoản</h2>
                <ul class="space-y-2 text-muted-foreground">
                    <li>
                        <Link :href="create()" :class="linkClass"
                            >Đăng tin</Link
                        >
                    </li>
                    <template v-if="user">
                        <li>
                            <Link :href="dashboard()" :class="linkClass"
                                >Tin đăng của tôi</Link
                            >
                        </li>
                        <li>
                            <Link
                                :href="notificationsIndex()"
                                :class="linkClass"
                                >Thông báo</Link
                            >
                        </li>
                        <li>
                            <Link :href="profileEdit()" :class="linkClass"
                                >Cài đặt tài khoản</Link
                            >
                        </li>
                    </template>
                    <template v-else>
                        <li>
                            <Link :href="login()" :class="linkClass"
                                >Đăng nhập</Link
                            >
                        </li>
                        <li>
                            <Link :href="register()" :class="linkClass"
                                >Đăng ký</Link
                            >
                        </li>
                    </template>
                </ul>
            </nav>
        </div>

        <div class="border-t">
            <div
                class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2 px-4 py-4 text-xs text-muted-foreground"
            >
                <span
                    >© {{ new Date().getFullYear() }}
                    {{ page.props.name }}</span
                >
                <span
                    >Giá hiển thị bằng đồng (đ). Thời gian theo giờ Việt
                    Nam.</span
                >
            </div>
        </div>
    </footer>
</template>
