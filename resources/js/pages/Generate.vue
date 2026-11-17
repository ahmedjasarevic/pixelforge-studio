<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Clock3,
    ImagePlus,
    Info,
    Plus,
    Sparkles,
    X,
} from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import type { CostEstimate } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Generate', href: '/generate' }],
    },
});

const props = defineProps<{
    pricing: {
        rates: Record<string, string>;
        tokens: Record<string, unknown>;
    } | null;
    pricing_error: string | null;
    initial_estimate: CostEstimate | null;
    defaults: {
        size: string;
        quality: string;
        prompt: string;
    };
    markup_percent: number;
}>();

const page = usePage();

const sizes = [
    {
        value: '1024x1024',
        label: 'Square',
        dimensions: '1024 × 1024',
        ratio: 1,
    },
    {
        value: '1536x1024',
        label: 'Landscape',
        dimensions: '1536 × 1024',
        ratio: 1.5,
    },
    {
        value: '1024x1536',
        label: 'Portrait',
        dimensions: '1024 × 1536',
        ratio: 2 / 3,
    },
];

const qualities = [
    {
        value: 'low',
        label: 'Low',
        blurb: 'Quick drafts',
        detail: '0–500 reasoning tokens',
    },
    {
        value: 'medium',
        label: 'Medium',
        blurb: 'Everyday images',
        detail: '200–2,000 reasoning tokens',
    },
    {
        value: 'high',
        label: 'High',
        blurb: 'Most detail',
        detail: '1,000–8,000 reasoning tokens',
    },
];

const promptIdeas = [
    'A lighthouse on a cliff at dawn, oil painting',
    'Isometric greenhouse floating in a cloud, soft light',
    'Bold abstract poster in violet and orange',
];

type ReferenceInput = { id: number; url: string };

const promptText = ref(props.defaults.prompt);
const selectedSize = ref(props.defaults.size);
const selectedQuality = ref(props.defaults.quality);
const referenceInputs = ref<ReferenceInput[]>([]);
let nextReferenceId = 1;

const generationForm = useForm({
    prompt: '',
    size: props.defaults.size,
    quality: props.defaults.quality,
    reference_images: [] as string[],
});

const processing = computed(() => generationForm.processing);
const errors = computed(
    () => generationForm.errors as Record<string, string | undefined>,
);

const estimate = ref<CostEstimate | null>(props.initial_estimate);
const estimateError = ref<string | null>(null);
const estimating = ref(false);
let debounceHandle: ReturnType<typeof setTimeout> | undefined;
let estimateRequest = 0;

const balance = computed(() => page.props.wallet?.balance ?? '$0.00');
const balancePicodollars = computed(
    () => page.props.wallet?.balance_picodollars ?? 0,
);

const notEnoughCredits = computed(
    () =>
        estimate.value !== null &&
        balancePicodollars.value < estimate.value.max_charge,
);

const canSubmit = computed(
    () =>
        props.pricing_error === null &&
        estimate.value !== null &&
        promptText.value.trim() !== '' &&
        !processing.value &&
        !notEnoughCredits.value,
);

function addReference(): void {
    if (referenceInputs.value.length < 4) {
        referenceInputs.value.push({ id: nextReferenceId++, url: '' });
    }
}

function removeReference(index: number): void {
    referenceInputs.value.splice(index, 1);
}

function useIdea(idea: string): void {
    promptText.value = idea;
}

function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

async function refreshEstimate(): Promise<void> {
    if (props.pricing_error !== null) {
        return;
    }

    const requestId = ++estimateRequest;
    estimating.value = true;

    try {
        const response = await fetch('/generate/estimate', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                prompt: promptText.value,
                size: selectedSize.value,
                quality: selectedQuality.value,
                reference_images: referenceInputs.value
                    .map((reference) => reference.url.trim())
                    .filter((url) => url !== ''),
            }),
        });

        const payload = await response.json();

        if (requestId !== estimateRequest) {
            return;
        }

        if (!response.ok) {
            const firstError = payload.errors
                ? Object.values(payload.errors)[0]
                : null;
            estimateError.value = Array.isArray(firstError)
                ? String(firstError[0])
                : (payload.message ?? 'Could not calculate a price.');
            return;
        }

        estimate.value = payload.estimate;
        estimateError.value = null;
    } catch {
        if (requestId === estimateRequest) {
            estimateError.value =
                'Could not reach the pricing service. Check your connection and try again.';
        }
    } finally {
        if (requestId === estimateRequest) {
            estimating.value = false;
        }
    }
}

watch(
    [
        () => promptText.value,
        () => selectedSize.value,
        () => selectedQuality.value,
        () =>
            referenceInputs.value.map((reference) => reference.url).join('\n'),
    ],
    () => {
        clearTimeout(debounceHandle);
        debounceHandle = setTimeout(refreshEstimate, 300);
    },
);

onMounted(refreshEstimate);
onUnmounted(() => clearTimeout(debounceHandle));

function submit(): void {
    if (!canSubmit.value) {
        return;
    }

    generationForm.prompt = promptText.value;
    generationForm.size = selectedSize.value;
    generationForm.quality = selectedQuality.value;
    generationForm.reference_images = referenceInputs.value
        .map((reference) => reference.url.trim())
        .filter((url) => url !== '');
    generationForm.post('/generate', { preserveScroll: true });
}
</script>

<template>
    <Head title="Generate" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 pb-24 md:p-6 lg:pb-6"
    >
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Generate an image
                </h1>
                <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                    Describe what you want. You pay Pixelforge's token cost plus
                    a {{ markup_percent }}% platform fee, and only when the
                    generation succeeds.
                </p>
            </div>
            <Link
                href="/history"
                :class="buttonVariants({ variant: 'outline', size: 'sm' })"
            >
                View history
                <ArrowRight class="size-4" />
            </Link>
        </header>

        <div
            v-if="pricing_error"
            class="flex items-start gap-3 rounded-lg border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive"
        >
            <Info class="mt-0.5 size-4 shrink-0" />
            <div>
                <p class="font-medium">New generations are paused</p>
                <p class="mt-0.5">{{ pricing_error }}</p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
            <div class="space-y-5">
                <Card>
                    <CardHeader>
                        <CardTitle>Prompt</CardTitle>
                        <CardDescription>
                            Be specific about subject, style, lighting and
                            composition.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <textarea
                            v-model="promptText"
                            rows="6"
                            maxlength="4000"
                            placeholder="A lighthouse on a cliff at dawn, oil painting, warm light, dramatic clouds…"
                            class="w-full resize-y rounded-md border border-input bg-transparent px-3 py-2.5 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-50 dark:bg-input/30"
                        />
                        <div
                            class="mt-2 flex items-center justify-between gap-3"
                        >
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="idea in promptIdeas"
                                    :key="idea"
                                    type="button"
                                    class="rounded-full border border-sidebar-border/70 px-2.5 py-1 text-left text-xs text-muted-foreground transition-colors hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700 dark:hover:border-violet-800 dark:hover:bg-violet-950/40 dark:hover:text-violet-300"
                                    @click="useIdea(idea)"
                                >
                                    {{ idea }}
                                </button>
                            </div>
                            <span
                                class="shrink-0 text-xs text-muted-foreground tabular-nums"
                            >
                                {{ promptText.length }} / 4,000
                            </span>
                        </div>
                        <InputError class="mt-2" :message="errors.prompt" />
                    </CardContent>
                </Card>

                <div class="grid gap-5 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Size</CardTitle>
                            <CardDescription>
                                Output dimensions in pixels.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="grid grid-cols-3 gap-2">
                            <button
                                v-for="size in sizes"
                                :key="size.value"
                                type="button"
                                :class="
                                    cn(
                                        'flex flex-col items-center gap-2 rounded-lg border bg-background p-2.5 text-center transition-all hover:border-violet-300 hover:bg-violet-50/40 dark:hover:border-violet-800 dark:hover:bg-violet-950/20',
                                        selectedSize === size.value
                                            ? 'border-violet-500 ring-2 ring-violet-500/20 dark:border-violet-400'
                                            : 'border-sidebar-border/70',
                                    )
                                "
                                @click="selectedSize = size.value"
                            >
                                <span
                                    class="flex h-11 w-full items-center justify-center"
                                >
                                    <span
                                        class="rounded-[3px] border-2 border-current opacity-40"
                                        :style="{
                                            width: `${Math.min(52, 40 * size.ratio)}px`,
                                            height: `${Math.min(44, 40 / size.ratio)}px`,
                                        }"
                                    />
                                </span>
                                <span class="text-xs leading-tight">
                                    <span class="block font-medium">{{
                                        size.label
                                    }}</span>
                                    <span
                                        class="block text-[10px] text-muted-foreground"
                                        >{{ size.dimensions }}</span
                                    >
                                </span>
                            </button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Quality</CardTitle>
                            <CardDescription>
                                Higher quality uses more output and reasoning
                                tokens.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-2">
                            <button
                                v-for="quality in qualities"
                                :key="quality.value"
                                type="button"
                                :class="
                                    cn(
                                        'flex w-full items-center justify-between gap-2 rounded-lg border px-3 py-2 text-left transition-all hover:border-violet-300 hover:bg-violet-50/40 dark:hover:border-violet-800 dark:hover:bg-violet-950/20',
                                        selectedQuality === quality.value
                                            ? 'border-violet-500 ring-2 ring-violet-500/20 dark:border-violet-400'
                                            : 'border-sidebar-border/70',
                                    )
                                "
                                @click="selectedQuality = quality.value"
                            >
                                <span>
                                    <span class="block text-sm font-medium">{{
                                        quality.label
                                    }}</span>
                                    <span
                                        class="block text-[11px] text-muted-foreground"
                                        >{{ quality.detail }}</span
                                    >
                                </span>
                                <span
                                    class="text-[11px] text-muted-foreground"
                                    >{{ quality.blurb }}</span
                                >
                            </button>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <ImagePlus class="size-4 text-muted-foreground" />
                            Reference images
                            <span
                                class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground"
                                >Optional</span
                            >
                        </CardTitle>
                        <CardDescription>
                            Up to 4 public image URLs to guide the style. Each
                            reference adds 765 input image tokens.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <div
                            v-for="(row, index) in referenceInputs"
                            :key="row.id"
                            class="flex items-center gap-2"
                        >
                            <input
                                v-model="row.url"
                                type="url"
                                :placeholder="`https://example.com/reference-${index + 1}.png`"
                                class="h-9 w-full min-w-0 flex-1 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                            />
                            <button
                                type="button"
                                :aria-label="`Remove reference image ${index + 1}`"
                                class="inline-flex size-9 shrink-0 items-center justify-center rounded-md border border-input bg-background text-sm shadow-xs transition-colors hover:bg-accent"
                                @click="removeReference(index)"
                            >
                                <X class="size-4" />
                            </button>
                        </div>

                        <Button
                            v-if="referenceInputs.length < 4"
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addReference"
                        >
                            <Plus class="size-4" />
                            Add reference image
                        </Button>

                        <p v-else class="text-xs text-muted-foreground">
                            Pixelforge accepts at most 4 reference images.
                        </p>

                        <InputError
                            v-for="(row, index) in referenceInputs"
                            :key="`error-${row.id}`"
                            :message="errors[`reference_images.${index}`]"
                        />
                        <InputError :message="errors.reference_images" />
                    </CardContent>
                </Card>
            </div>

            <aside class="space-y-4 self-start lg:sticky lg:top-4">
                <Card
                    class="overflow-hidden border-violet-200/70 shadow-sm dark:border-violet-900/60"
                >
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base">Cost estimate</CardTitle>
                        <CardDescription>
                            Provider cost + {{ markup_percent }}% markup. The
                            final price depends on actual token usage.
                        </CardDescription>
                    </CardHeader>

                    <CardContent v-if="estimate">
                        <div
                            class="relative overflow-hidden rounded-xl bg-gradient-to-br from-violet-600 via-violet-600 to-fuchsia-600 p-4 text-white shadow-inner"
                        >
                            <div
                                class="absolute -top-8 -right-8 size-24 rounded-full bg-white/10 blur-2xl"
                            />
                            <div class="flex items-start justify-between">
                                <p
                                    class="text-[11px] font-medium tracking-wide text-white/75 uppercase"
                                >
                                    Estimated price
                                </p>
                                <span
                                    class="rounded-full bg-white/15 px-2 py-0.5 text-[10px] font-medium text-white/90 capitalize"
                                    >{{ selectedQuality }} ·
                                    {{ selectedSize }}</span
                                >
                            </div>
                            <p
                                class="mt-1 text-3xl font-semibold tracking-tight tabular-nums"
                            >
                                {{ estimate.charge_formatted }}
                            </p>
                            <p class="mt-1 text-[11px] text-white/75">
                                Up to
                                {{ estimate.max_charge_formatted }} is held
                                while it runs. Unused credits come straight
                                back.
                            </p>
                        </div>

                        <div class="mt-4 space-y-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground"
                                    >Pixelforge usage</span
                                >
                                <span class="tabular-nums">{{
                                    estimate.provider_cost_formatted
                                }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground"
                                    >Platform fee ({{
                                        estimate.markup_percent
                                    }}%)</span
                                >
                                <span class="tabular-nums">{{
                                    estimate.markup_formatted
                                }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between border-t border-sidebar-border/70 pt-2 font-medium"
                            >
                                <span>You pay</span>
                                <span class="tabular-nums">{{
                                    estimate.charge_formatted
                                }}</span>
                            </div>
                        </div>

                        <div
                            class="mt-4 space-y-1.5 rounded-lg bg-muted/50 p-3 text-[11px] text-muted-foreground"
                        >
                            <div class="flex justify-between gap-2">
                                <span>Output tokens (exact)</span>
                                <span class="tabular-nums">{{
                                    estimate.tokens.output.toLocaleString()
                                }}</span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span>Prompt text (estimated)</span>
                                <span class="tabular-nums"
                                    >~{{
                                        estimate.tokens.input_text.toLocaleString()
                                    }}</span
                                >
                            </div>
                            <div
                                v-if="estimate.tokens.input_image > 0"
                                class="flex justify-between gap-2"
                            >
                                <span>Reference images (exact)</span>
                                <span class="tabular-nums">{{
                                    estimate.tokens.input_image.toLocaleString()
                                }}</span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span>Reasoning (estimated)</span>
                                <span class="tabular-nums"
                                    >~{{
                                        estimate.tokens.reasoning.toLocaleString()
                                    }}</span
                                >
                            </div>
                        </div>

                        <p
                            v-if="estimateError"
                            class="mt-3 text-xs text-destructive"
                        >
                            {{ estimateError }}
                        </p>
                        <p
                            v-else
                            class="mt-3 text-[11px] leading-relaxed text-muted-foreground"
                        >
                            Pixelforge never tells us the reasoning and exact
                            text token counts until the generation finishes. The
                            final charge will be at or below the held amount.
                        </p>
                    </CardContent>

                    <CardContent v-else class="py-8 text-center">
                        <p class="text-sm font-medium">
                            Pricing is unavailable
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            New generations are paused until Pixelforge's
                            pricing endpoint responds.
                        </p>
                    </CardContent>

                    <CardFooter class="flex-col items-stretch gap-2">
                        <Button
                            type="button"
                            size="lg"
                            :disabled="!canSubmit"
                            class="w-full bg-gradient-to-r from-violet-600 to-fuchsia-600 text-white shadow-sm hover:from-violet-700 hover:to-fuchsia-700"
                            @click="submit"
                        >
                            <Spinner v-if="processing" />
                            <Sparkles v-else class="size-4" />
                            <span v-if="processing">Submitting…</span>
                            <span v-else-if="estimate"
                                >Generate ·
                                {{ estimate.charge_formatted }}</span
                            >
                            <span v-else>Generate</span>
                        </Button>

                        <p
                            v-if="errors.credits"
                            class="text-center text-xs text-destructive"
                        >
                            {{ errors.credits }}
                        </p>
                        <p
                            v-else-if="notEnoughCredits && estimate"
                            class="text-center text-xs text-destructive"
                        >
                            You need at least
                            {{ estimate.max_charge_formatted }} available (you
                            have {{ balance }}). Top up on the credits page.
                        </p>
                        <p
                            class="text-center text-[11px] text-muted-foreground"
                        >
                            Available balance:
                            <span class="font-medium text-foreground">{{
                                balance
                            }}</span>
                        </p>
                    </CardFooter>
                </Card>

                <Card class="border-sidebar-border/70 bg-muted/30 shadow-none">
                    <CardContent
                        class="flex items-start gap-3 pt-4 text-xs text-muted-foreground"
                    >
                        <Clock3 class="mt-0.5 size-4 shrink-0" />
                        <div class="space-y-1">
                            <p class="font-medium text-foreground">
                                What happens next
                            </p>
                            <p>
                                Most images take 5–30 seconds. Long prompts can
                                take up to 90. You can leave the history page —
                                it updates automatically when the image is
                                ready.
                            </p>
                            <p
                                class="font-medium text-emerald-600 dark:text-emerald-400"
                            >
                                Failed generations are always free.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </aside>
        </div>

        <div
            class="fixed inset-x-0 bottom-0 z-30 border-t border-sidebar-border/70 bg-background/95 px-4 py-3 shadow-[0_-8px_24px_rgba(0,0,0,0.06)] backdrop-blur lg:hidden"
        >
            <div class="mx-auto flex max-w-3xl items-center gap-3">
                <div class="min-w-0">
                    <p
                        class="text-[10px] tracking-wide text-muted-foreground uppercase"
                    >
                        Estimated
                    </p>
                    <p class="text-lg font-semibold tabular-nums">
                        {{ estimate?.charge_formatted ?? '—' }}
                    </p>
                </div>
                <Button
                    type="button"
                    size="lg"
                    :disabled="!canSubmit"
                    class="flex-1 bg-gradient-to-r from-violet-600 to-fuchsia-600 text-white shadow-sm hover:from-violet-700 hover:to-fuchsia-700"
                    @click="submit"
                >
                    <Spinner v-if="processing" />
                    <Sparkles v-else class="size-4" />
                    Generate
                </Button>
            </div>
        </div>
    </div>
</template>
