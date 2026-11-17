<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, History, RefreshCw, Sparkles } from '@lucide/vue';
import GenerationCard from '@/components/GenerationCard.vue';
import { Button, buttonVariants } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { Generation } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'History', href: '/history' }],
    },
});

const props = defineProps<{
    generations: Generation[];
    has_pending: boolean;
    summary: {
        total_generations: number;
        in_progress: number;
        total_charged: number;
        total_charged_formatted: string;
    };
}>();

const refreshing = ref(false);
let pollHandle: ReturnType<typeof setInterval> | undefined;

function refresh(): void {
    if (refreshing.value) {
        return;
    }

    refreshing.value = true;
    router.reload({
        only: ['generations', 'has_pending', 'summary'],
        onFinish: () => {
            refreshing.value = false;
        },
    });
}

onMounted(() => {
    pollHandle = setInterval(() => {
        if (props.has_pending && document.visibilityState === 'visible') {
            refresh();
        }
    }, 4000);
});

onUnmounted(() => {
    clearInterval(pollHandle);
});
</script>

<template>
    <Head title="History" />

    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        Your generations
                    </h1>
                    <span
                        v-if="has_pending"
                        class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-medium text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300"
                    >
                        <span class="relative flex size-1.5">
                            <span
                                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
                            />
                            <span
                                class="relative inline-flex size-1.5 rounded-full bg-emerald-500"
                            />
                        </span>
                        Live
                    </span>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    Live status and the exact amount each generation cost you.
                    Failed generations are free.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="refreshing"
                    @click="refresh"
                >
                    <Spinner v-if="refreshing" />
                    <RefreshCw v-else class="size-4" />
                    Refresh
                </Button>
                <Link
                    href="/generate"
                    :class="buttonVariants({ variant: 'default', size: 'sm' })"
                >
                    <Sparkles class="size-4" />
                    New generation
                </Link>
            </div>
        </header>

        <div class="grid gap-3 sm:grid-cols-3">
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card px-4 py-3 shadow-sm"
            >
                <p class="text-[11px] font-medium text-muted-foreground">
                    All generations
                </p>
                <p class="mt-0.5 text-xl font-semibold tabular-nums">
                    {{ summary.total_generations }}
                </p>
            </div>
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card px-4 py-3 shadow-sm"
            >
                <p class="text-[11px] font-medium text-muted-foreground">
                    In progress
                </p>
                <p class="mt-0.5 text-xl font-semibold tabular-nums">
                    {{ summary.in_progress }}
                </p>
            </div>
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card px-4 py-3 shadow-sm"
            >
                <p class="text-[11px] font-medium text-muted-foreground">
                    Credits spent
                </p>
                <p class="mt-0.5 text-xl font-semibold tabular-nums">
                    {{ summary.total_charged_formatted }}
                </p>
            </div>
        </div>

        <div
            v-if="generations.length > 0"
            class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3"
        >
            <GenerationCard
                v-for="generation in generations"
                :key="generation.id"
                :generation="generation"
            />
        </div>

        <div
            v-else
            class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-sidebar-border bg-muted/20 px-6 py-20 text-center"
        >
            <div
                class="flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white shadow-lg shadow-violet-500/20"
            >
                <History class="size-6" />
            </div>
            <h2 class="mt-4 text-lg font-semibold">No generations yet</h2>
            <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                Write a prompt and Pixelforge will create your first image. You
                only pay if it succeeds.
            </p>
            <Link
                href="/generate"
                :class="
                    buttonVariants({
                        variant: 'default',
                        size: 'default',
                    }) + ' mt-5'
                "
            >
                Generate your first image
                <ArrowRight class="size-4" />
            </Link>
        </div>
    </div>
</template>
