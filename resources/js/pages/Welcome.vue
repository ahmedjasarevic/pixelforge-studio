<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    CircleDollarSign,
    Clock3,
    Sparkles,
    WandSparkles,
} from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { buttonVariants } from '@/components/ui/button';

const page = usePage();
const user = page.props.auth.user;
</script>

<template>
    <Head title="Your prompt. Priced honestly.">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div class="min-h-screen bg-[#fbfaff] text-foreground dark:bg-[#0b0910]">
        <header
            class="mx-auto flex w-full max-w-6xl items-center justify-between px-5 py-5"
        >
            <Link href="/" class="flex items-center gap-2.5">
                <span
                    class="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white shadow-lg shadow-violet-500/25"
                >
                    <AppLogoIcon class="size-5" />
                </span>
                <span class="font-semibold tracking-tight">{{
                    page.props.name
                }}</span>
            </Link>

            <nav class="flex items-center gap-2 text-sm">
                <a
                    href="#how-it-works"
                    class="hidden rounded-md px-3 py-2 text-muted-foreground transition-colors hover:text-foreground sm:inline-block"
                    >How it works</a
                >
                <a
                    href="#pricing"
                    class="hidden rounded-md px-3 py-2 text-muted-foreground transition-colors hover:text-foreground sm:inline-block"
                    >Pricing</a
                >
                <template v-if="user">
                    <Link
                        href="/generate"
                        :class="
                            buttonVariants({ variant: 'default', size: 'sm' })
                        "
                    >
                        Open studio
                        <ArrowRight class="size-4" />
                    </Link>
                </template>
                <template v-else>
                    <Link
                        href="/login"
                        :class="
                            buttonVariants({ variant: 'ghost', size: 'sm' })
                        "
                        >Log in</Link
                    >
                    <Link
                        href="/register"
                        :class="
                            buttonVariants({ variant: 'default', size: 'sm' })
                        "
                        >Get started</Link
                    >
                </template>
            </nav>
        </header>

        <main class="mx-auto w-full max-w-6xl px-5">
            <section
                class="grid items-center gap-12 py-14 lg:grid-cols-[1.05fr_0.95fr] lg:py-24"
            >
                <div>
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-violet-200/70 bg-white/70 px-3 py-1 text-xs font-medium text-violet-700 shadow-sm backdrop-blur dark:border-violet-900/60 dark:bg-violet-950/40 dark:text-violet-300"
                    >
                        <Sparkles class="size-3.5" />
                        Powered by Pixelforge
                    </div>

                    <h1
                        class="mt-5 text-4xl leading-[1.08] font-semibold tracking-tight sm:text-5xl lg:text-6xl"
                    >
                        Your prompt.
                        <span
                            class="bg-gradient-to-r from-violet-600 via-fuchsia-500 to-amber-500 bg-clip-text text-transparent"
                            >Priced honestly.</span
                        >
                    </h1>

                    <p
                        class="mt-5 max-w-xl text-base leading-relaxed text-muted-foreground sm:text-lg"
                    >
                        Generate images with Pixelforge and see the price before
                        you click. You pay provider cost plus a transparent 25%
                        markup — and nothing when a generation fails.
                    </p>

                    <div class="mt-7 flex flex-wrap items-center gap-3">
                        <Link
                            :href="user ? '/generate' : '/register'"
                            :class="
                                buttonVariants({
                                    variant: 'default',
                                    size: 'lg',
                                }) +
                                ' bg-gradient-to-r from-violet-600 to-fuchsia-600 text-white shadow-lg shadow-violet-500/25 hover:from-violet-700 hover:to-fuchsia-700'
                            "
                        >
                            <WandSparkles class="size-4" />
                            {{ user ? 'Open the studio' : 'Start creating' }}
                            <ArrowRight class="size-4" />
                        </Link>
                        <a
                            href="#pricing"
                            :class="
                                buttonVariants({
                                    variant: 'outline',
                                    size: 'lg',
                                })
                            "
                            >See how pricing works</a
                        >
                    </div>

                    <div
                        class="mt-7 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-muted-foreground"
                    >
                        <span class="inline-flex items-center gap-1.5">
                            <Check class="size-3.5 text-emerald-500" />
                            $5 in demo credits on sign-up
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <Check class="size-3.5 text-emerald-500" />
                            No real payments
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <Check class="size-3.5 text-emerald-500" />
                            Every cost auditable
                        </span>
                    </div>
                </div>

                <div class="relative">
                    <div
                        class="absolute -inset-6 -z-10 rounded-[2rem] bg-gradient-to-br from-violet-300/40 via-fuchsia-300/30 to-amber-200/40 blur-2xl dark:from-violet-900/40 dark:via-fuchsia-900/30 dark:to-amber-900/20"
                    />
                    <div
                        class="overflow-hidden rounded-2xl border border-white/70 bg-white/80 shadow-2xl shadow-violet-900/10 backdrop-blur dark:border-white/10 dark:bg-white/5"
                    >
                        <div class="relative aspect-square overflow-hidden">
                            <div
                                class="absolute inset-0 bg-[radial-gradient(circle_at_30%_25%,#f5d0fe_0%,transparent_45%),radial-gradient(circle_at_75%_30%,#c4b5fd_0%,transparent_45%),radial-gradient(circle_at_55%_80%,#fcd34d_0%,transparent_40%),linear-gradient(135deg,#7c3aed_0%,#db2777_55%,#f59e0b_100%)]"
                            />
                            <div
                                class="absolute top-[18%] left-[22%] size-32 rounded-full border border-white/30 bg-white/20 backdrop-blur-md"
                            />
                            <div
                                class="absolute right-[16%] bottom-[20%] size-24 rotate-12 rounded-3xl border border-white/30 bg-white/15 backdrop-blur-md"
                            />
                            <div
                                class="absolute right-[30%] bottom-[38%] size-14 rounded-full bg-white/40 blur-[1px]"
                            />

                            <span
                                class="absolute top-4 left-4 inline-flex items-center gap-1.5 rounded-full border border-emerald-200/80 bg-emerald-50/90 px-2.5 py-1 text-[11px] font-medium text-emerald-700 shadow-sm backdrop-blur"
                            >
                                <span
                                    class="size-1.5 rounded-full bg-emerald-500"
                                />
                                Succeeded
                            </span>
                        </div>

                        <div class="space-y-3 p-4">
                            <p class="text-sm font-medium">
                                “A lighthouse on a cliff at dawn, oil painting”
                            </p>
                            <div
                                class="flex items-center justify-between text-xs text-muted-foreground"
                            >
                                <span>1024 × 1024 · medium quality</span>
                                <span>5–30s</span>
                            </div>
                            <div
                                class="flex items-center justify-between rounded-lg bg-muted/60 px-3 py-2.5"
                            >
                                <div>
                                    <p
                                        class="text-[10px] tracking-wide text-muted-foreground uppercase"
                                    >
                                        Charged
                                    </p>
                                    <p
                                        class="text-lg font-semibold tabular-nums"
                                    >
                                        $0.016416
                                    </p>
                                </div>
                                <div
                                    class="text-right text-[10px] text-muted-foreground"
                                >
                                    <p>Provider $0.013133</p>
                                    <p>+ 25% platform fee</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section
                id="how-it-works"
                class="grid gap-4 border-t border-violet-100 py-14 sm:grid-cols-3 dark:border-violet-950/60"
            >
                <div
                    class="rounded-2xl border border-sidebar-border/60 bg-white/60 p-5 shadow-sm backdrop-blur dark:bg-white/[0.03]"
                >
                    <div
                        class="flex size-10 items-center justify-center rounded-xl bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300"
                    >
                        <CircleDollarSign class="size-5" />
                    </div>
                    <h3 class="mt-4 font-semibold">Know the price first</h3>
                    <p
                        class="mt-1.5 text-sm leading-relaxed text-muted-foreground"
                    >
                        Every prompt, size, quality and reference image is
                        priced before you submit. See provider cost and our fee
                        separately.
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-sidebar-border/60 bg-white/60 p-5 shadow-sm backdrop-blur dark:bg-white/[0.03]"
                >
                    <div
                        class="flex size-10 items-center justify-center rounded-xl bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-950/60 dark:text-fuchsia-300"
                    >
                        <Clock3 class="size-5" />
                    </div>
                    <h3 class="mt-4 font-semibold">Watch it happen</h3>
                    <p
                        class="mt-1.5 text-sm leading-relaxed text-muted-foreground"
                    >
                        Queue, processing and completion are live. Webhooks are
                        signature-verified and safely handled even when they
                        arrive twice.
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-sidebar-border/60 bg-white/60 p-5 shadow-sm backdrop-blur dark:bg-white/[0.03]"
                >
                    <div
                        class="flex size-10 items-center justify-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300"
                    >
                        <Check class="size-5" />
                    </div>
                    <h3 class="mt-4 font-semibold">Pay only for success</h3>
                    <p
                        class="mt-1.5 text-sm leading-relaxed text-muted-foreground"
                    >
                        A temporary hold covers the worst case. When the image
                        is ready, the exact token cost replaces it. Failures are
                        free.
                    </p>
                </div>
            </section>

            <section
                id="pricing"
                class="mb-16 overflow-hidden rounded-3xl border border-violet-200/60 bg-white/70 p-6 shadow-xl shadow-violet-900/5 backdrop-blur sm:p-10 dark:border-violet-900/50 dark:bg-white/[0.03]"
            >
                <div class="grid gap-8 lg:grid-cols-2">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wide text-violet-600 uppercase dark:text-violet-400"
                        >
                            Transparent by design
                        </p>
                        <h2
                            class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl"
                        >
                            The math, in the open.
                        </h2>
                        <p
                            class="mt-3 text-sm leading-relaxed text-muted-foreground"
                        >
                            Pixelforge bills per token: $5 per 1M input text
                            tokens, $10 per 1M input image tokens, $40 per 1M
                            output tokens and $12.50 per 1M reasoning tokens. We
                            add 25% and show both numbers.
                        </p>
                    </div>

                    <div
                        class="rounded-2xl bg-[#0f0b18] p-5 font-mono text-xs text-violet-100/90 shadow-inner"
                    >
                        <p class="text-violet-300/60">
                            // high quality, 1536×1024
                        </p>
                        <div class="mt-3 flex justify-between">
                            <span>text tokens</span><span>~115</span>
                        </div>
                        <div class="mt-1 flex justify-between">
                            <span>output tokens</span><span>6,450</span>
                        </div>
                        <div class="mt-1 flex justify-between">
                            <span>reasoning tokens</span><span>≤ 8,000</span>
                        </div>
                        <div
                            class="mt-3 flex justify-between border-t border-white/10 pt-3"
                        >
                            <span>Pixelforge max</span><span>$0.373875</span>
                        </div>
                        <div class="mt-1 flex justify-between text-emerald-300">
                            <span>you pay max (+25%)</span
                            ><span>$0.467343</span>
                        </div>
                        <p class="mt-3 text-[10px] text-violet-200/50">
                            Actual reasoning and text tokens are known only
                            after the generation finishes.
                        </p>
                    </div>
                </div>
            </section>
        </main>

        <footer
            class="border-t border-violet-100 py-8 dark:border-violet-950/60"
        >
            <div
                class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-3 px-5 text-xs text-muted-foreground"
            >
                <p>{{ page.props.name }} · a PromptEdit assessment build</p>
                <div class="flex items-center gap-4">
                    <a
                        href="https://pixelforge.promptedit.dev/docs"
                        class="transition-colors hover:text-foreground"
                        >Pixelforge API</a
                    >
                    <a
                        href="https://pixelforge.promptedit.dev/assessment"
                        class="transition-colors hover:text-foreground"
                        >Assessment</a
                    >
                </div>
            </div>
        </footer>
    </div>
</template>
