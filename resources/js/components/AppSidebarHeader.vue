<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Plus, Wallet } from '@lucide/vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { buttonVariants } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div
            v-if="page.props.auth.user"
            class="ml-auto flex items-center gap-2"
        >
            <Link
                href="/credits"
                class="flex items-center gap-2 rounded-full border border-sidebar-border/70 bg-background px-3 py-1.5 text-sm shadow-xs transition-colors hover:bg-accent"
                title="Available credits"
            >
                <Wallet class="size-4 text-muted-foreground" />
                <span class="font-semibold tabular-nums">{{
                    page.props.wallet?.balance ?? '$0.00'
                }}</span>
                <span class="hidden text-xs text-muted-foreground sm:inline"
                    >credits</span
                >
            </Link>
            <Link
                href="/credits"
                :class="
                    buttonVariants({
                        variant: 'outline',
                        size: 'sm',
                    })
                "
            >
                <Plus class="size-4" />
                <span class="hidden sm:inline">Buy credits</span>
            </Link>
        </div>
    </header>
</template>
