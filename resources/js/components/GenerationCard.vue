<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
    AlertTriangle,
    Check,
    Clock3,
    ImageOff,
    LoaderCircle,
    X,
} from '@lucide/vue';
import type { Generation, GenerationStatus } from '@/types';

const props = defineProps<{
    generation: Generation;
}>();

const imageFailed = ref(false);

watch(
    () => props.generation.image_url,
    () => {
        imageFailed.value = false;
    },
);

const sizeLabels: Record<string, string> = {
    '1024x1024': '1024 × 1024',
    '1536x1024': '1536 × 1024',
    '1024x1536': '1024 × 1536',
};

const aspectRatio = computed(() => {
    switch (props.generation.size) {
        case '1536x1024':
            return '3 / 2';
        case '1024x1536':
            return '2 / 3';
        default:
            return '1 / 1';
    }
});

const statusClasses: Record<GenerationStatus, string> = {
    queued: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300',
    processing:
        'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/60 dark:bg-sky-950/40 dark:text-sky-300',
    succeeded:
        'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300',
    failed: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300',
};

const showImage = computed(
    () =>
        props.generation.status === 'succeeded' &&
        props.generation.image_url !== null &&
        !imageFailed.value,
);

const pending = computed(
    () =>
        props.generation.status === 'queued' ||
        props.generation.status === 'processing',
);

const formattedDate = computed(() => {
    if (!props.generation.created_at) {
        return '';
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(props.generation.created_at));
});

const usageLabel = computed(() => {
    const usage = props.generation.usage;

    if (!usage) {
        return null;
    }

    return `${(usage.input_text_tokens + usage.input_image_tokens).toLocaleString()} input · ${usage.output_tokens.toLocaleString()} output · ${usage.reasoning_tokens.toLocaleString()} reasoning tokens`;
});
</script>

<template>
    <article
        class="flex flex-col overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm transition-shadow hover:shadow-md"
    >
        <div
            class="relative overflow-hidden bg-gradient-to-br from-violet-100 via-fuchsia-50 to-amber-50 dark:from-violet-950/40 dark:via-fuchsia-950/30 dark:to-amber-950/20"
            :style="{ aspectRatio }"
        >
            <img
                v-if="showImage"
                :src="generation.image_url!"
                :alt="generation.prompt"
                class="h-full w-full object-cover"
                loading="lazy"
                @error="imageFailed = true"
            />

            <div
                v-else
                class="flex h-full w-full flex-col items-center justify-center gap-2 p-6 text-center text-muted-foreground"
            >
                <template v-if="pending">
                    <div
                        class="flex size-12 items-center justify-center rounded-full bg-background/70 shadow-sm backdrop-blur"
                    >
                        <LoaderCircle
                            class="size-5 animate-spin text-violet-600"
                        />
                    </div>
                    <p class="text-xs font-medium text-foreground">
                        {{
                            generation.status === 'queued'
                                ? 'Waiting for Pixelforge…'
                                : 'Pixelforge is drawing…'
                        }}
                    </p>
                    <p class="text-[11px]">Usually 5–30 seconds</p>
                </template>

                <template v-else-if="generation.status === 'failed'">
                    <div
                        class="flex size-12 items-center justify-center rounded-full bg-rose-100 text-rose-600 dark:bg-rose-950/60 dark:text-rose-300"
                    >
                        <X class="size-5" />
                    </div>
                    <p class="text-xs font-medium text-foreground">
                        Generation failed
                    </p>
                    <p class="text-[11px]">You were not charged.</p>
                </template>

                <template v-else>
                    <ImageOff class="size-6 opacity-60" />
                    <p class="text-xs font-medium text-foreground">
                        Image unavailable
                    </p>
                    <p class="max-w-[240px] text-[11px]">
                        Pixelforge's image links expire 24 hours after
                        completion.
                    </p>
                </template>
            </div>

            <span
                :class="[
                    'absolute top-3 left-3 inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-medium shadow-sm backdrop-blur',
                    statusClasses[generation.status],
                ]"
            >
                <LoaderCircle v-if="pending" class="size-3 animate-spin" />
                <Check
                    v-else-if="generation.status === 'succeeded'"
                    class="size-3"
                />
                <X v-else class="size-3" />
                {{ generation.status_label }}
            </span>

            <span
                class="absolute right-3 bottom-3 rounded-full bg-black/55 px-2 py-0.5 text-[10px] font-medium text-white backdrop-blur"
            >
                {{ sizeLabels[generation.size] ?? generation.size }} ·
                {{ generation.quality
                }}<template v-if="generation.reference_images.length > 0">
                    · {{ generation.reference_images.length }} ref</template
                >
            </span>
        </div>

        <div class="flex flex-1 flex-col gap-3 p-4">
            <p class="line-clamp-3 text-sm leading-relaxed">
                {{ generation.prompt }}
            </p>

            <div
                v-if="
                    generation.status === 'failed' && generation.error_message
                "
                class="flex items-start gap-2 rounded-md border border-rose-200/70 bg-rose-50/70 p-2.5 text-[11px] leading-relaxed text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-300"
            >
                <AlertTriangle class="mt-0.5 size-3.5 shrink-0" />
                <div>
                    <p class="font-medium">
                        {{
                            generation.error_code === 'generation_failed'
                                ? 'Pixelforge could not generate this image'
                                : 'Something went wrong'
                        }}
                    </p>
                    <p class="mt-0.5 opacity-90">
                        {{ generation.error_message }}
                    </p>
                </div>
            </div>

            <div class="mt-auto space-y-2">
                <div
                    class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-xs"
                >
                    <template v-if="generation.status === 'succeeded'">
                        <span class="font-medium">
                            Charged
                            <span class="text-foreground tabular-nums">{{
                                generation.charged_amount_formatted
                            }}</span>
                        </span>
                        <span class="text-muted-foreground">
                            est.
                            {{ generation.estimated_charge_formatted }}
                        </span>
                    </template>
                    <template v-else-if="generation.status === 'failed'">
                        <span
                            class="rounded-full bg-emerald-50 px-2 py-0.5 font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
                            >Free — not charged</span
                        >
                        <span class="text-muted-foreground">
                            est. {{ generation.estimated_charge_formatted }}
                        </span>
                    </template>
                    <template v-else>
                        <span class="text-muted-foreground">
                            Estimated
                            <span class="font-medium text-foreground">{{
                                generation.estimated_charge_formatted
                            }}</span>
                        </span>
                        <span class="text-muted-foreground">
                            Up to {{ generation.max_charge_formatted }} held
                        </span>
                    </template>
                </div>

                <div
                    v-if="usageLabel"
                    class="text-[10px] text-muted-foreground tabular-nums"
                >
                    {{ usageLabel }}
                </div>

                <div
                    class="flex items-center gap-1.5 border-t border-sidebar-border/60 pt-2 text-[10px] text-muted-foreground"
                >
                    <Clock3 class="size-3" />
                    <span>{{ formattedDate }}</span>
                    <span class="ml-auto">#{{ generation.id }}</span>
                </div>
            </div>
        </div>
    </article>
</template>
