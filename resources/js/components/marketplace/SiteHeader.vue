<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    BellRing,
    ChevronDown,
    Gavel,
    Gift,
    LayoutGrid,
    Menu,
    Plus,
    Search,
    Shield,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import CitySelect from '@/components/marketplace/CitySelect.vue';
import NotificationBell from '@/components/marketplace/NotificationBell.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useAuthUser } from '@/composables/useAuthUser';
import { getInitials } from '@/composables/useInitials';
import { dashboard, home, login, register } from '@/routes';
import { show as categoryShow } from '@/routes/categories';
import { index as giveawaysIndex } from '@/routes/giveaways';
import { index as savedSearchesIndex } from '@/routes/saved-searches';
import { create, index } from '@/routes/listings';

const page = usePage();
const user = useAuthUser();
const categories = computed(() => page.props.categories);

const query = new URLSearchParams(page.url.split('?')[1] ?? '');
const term = ref(query.get('q') ?? '');
const cityId = ref<number | null>(
    query.get('city_id') ? Number(query.get('city_id')) : null,
);

function search() {
    router.get(
        index({
            query: {
                q: term.value || undefined,
                city_id: cityId.value ?? undefined,
            },
        }).url,
    );
}
</script>

<template>
    <header class="border-b bg-background">
        <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4">
            <!-- Mobile: categories in a sheet -->
            <Sheet>
                <SheetTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="lg:hidden"
                        aria-label="Danh mục"
                    >
                        <Menu class="size-5" />
                    </Button>
                </SheetTrigger>
                <SheetContent side="left" class="w-72 overflow-y-auto p-6">
                    <SheetHeader class="p-0 text-left">
                        <SheetTitle>Danh mục</SheetTitle>
                    </SheetHeader>
                    <nav class="mt-4 flex flex-col gap-4 text-sm">
                        <Link
                            :href="giveawaysIndex()"
                            class="flex items-center gap-2 font-medium hover:underline"
                        >
                            <Gift class="size-4" />
                            Quà tặng
                        </Link>
                        <div v-for="category in categories" :key="category.id">
                            <Link
                                :href="categoryShow(category.slug)"
                                class="font-medium hover:underline"
                            >
                                {{ category.name }}
                            </Link>
                            <div class="mt-1 flex flex-col gap-1 pl-3">
                                <Link
                                    v-for="child in category.children"
                                    :key="child.id"
                                    :href="categoryShow(child.slug)"
                                    class="text-muted-foreground hover:text-foreground"
                                >
                                    {{ child.name }}
                                </Link>
                            </div>
                        </div>
                    </nav>
                </SheetContent>
            </Sheet>

            <Link
                :href="home()"
                class="flex shrink-0 items-center gap-2 font-semibold"
            >
                <span
                    class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground"
                >
                    <Gavel class="size-4" />
                </span>
                <span class="hidden sm:inline">{{ page.props.name }}</span>
            </Link>

            <form
                class="flex min-w-0 flex-1 items-center gap-2"
                role="search"
                @submit.prevent="search"
            >
                <div class="hidden w-44 shrink-0 md:block">
                    <CitySelect v-model="cityId" all-label="Toàn quốc" />
                </div>
                <div class="relative min-w-0 flex-1">
                    <Search
                        class="absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                    />
                    <Input
                        v-model="term"
                        type="search"
                        name="q"
                        placeholder="Tìm sản phẩm đấu giá…"
                        class="pl-8"
                    />
                </div>
            </form>

            <Button as-child class="shrink-0">
                <Link :href="create()">
                    <Plus class="size-4" />
                    <span class="hidden sm:inline">Đăng tin</span>
                </Link>
            </Button>

            <NotificationBell v-if="user" />

            <DropdownMenu v-if="user">
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-9 shrink-0 rounded-full"
                        aria-label="Tài khoản"
                    >
                        <Avatar class="size-8">
                            <AvatarImage
                                v-if="user.avatar"
                                :src="user.avatar"
                                :alt="user.name"
                            />
                            <AvatarFallback>{{
                                getInitials(user.name)
                            }}</AvatarFallback>
                        </Avatar>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56">
                    <DropdownMenuItem as-child>
                        <Link
                            :href="dashboard()"
                            class="block w-full cursor-pointer"
                        >
                            <LayoutGrid class="mr-2 size-4" />
                            Tin đăng của tôi
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem as-child>
                        <Link
                            :href="savedSearchesIndex()"
                            class="block w-full cursor-pointer"
                        >
                            <BellRing class="mr-2 size-4" />
                            Tìm kiếm đã lưu
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem v-if="user.role !== 'user'" as-child>
                        <a href="/admin" class="block w-full cursor-pointer">
                            <Shield class="mr-2 size-4" />
                            Quản trị
                        </a>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
            <div v-else class="flex shrink-0 items-center gap-1">
                <Button variant="ghost" as-child>
                    <Link :href="login()">Đăng nhập</Link>
                </Button>
                <Button
                    variant="outline"
                    as-child
                    class="hidden sm:inline-flex"
                >
                    <Link :href="register()">Đăng ký</Link>
                </Button>
            </div>
        </div>

        <!-- Desktop: top-level categories, children on hover -->
        <nav class="hidden border-t lg:block" aria-label="Danh mục">
            <ul class="mx-auto flex max-w-7xl items-center gap-1 px-4 text-sm">
                <li
                    v-for="category in categories"
                    :key="category.id"
                    class="group relative"
                >
                    <Link
                        :href="categoryShow(category.slug)"
                        class="flex items-center gap-1 px-3 py-2 hover:text-primary"
                    >
                        {{ category.name }}
                        <ChevronDown
                            v-if="category.children.length"
                            class="size-3.5 text-muted-foreground"
                        />
                    </Link>
                    <ul
                        v-if="category.children.length"
                        class="invisible absolute top-full left-0 z-20 min-w-48 rounded-md border bg-popover p-1 opacity-0 shadow-md transition group-focus-within:visible group-focus-within:opacity-100 group-hover:visible group-hover:opacity-100"
                    >
                        <li v-for="child in category.children" :key="child.id">
                            <Link
                                :href="categoryShow(child.slug)"
                                class="block rounded-sm px-3 py-1.5 hover:bg-accent"
                            >
                                {{ child.name }}
                            </Link>
                        </li>
                    </ul>
                </li>
                <li class="ml-auto">
                    <Link
                        :href="giveawaysIndex()"
                        class="flex items-center gap-1.5 px-3 py-2 font-medium hover:text-primary"
                    >
                        <Gift class="size-4" />
                        Quà tặng
                    </Link>
                </li>
            </ul>
        </nav>
    </header>
</template>
