<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    ChevronDown,
    CircleDollarSign,
    RefreshCw,
    TrendingUp,
    Users,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { AdminReport } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Admin report', href: '/admin/report' }],
    },
});

const props = defineProps<{
    report: AdminReport;
}>();

const from = ref(props.report.range.from);
const to = ref(props.report.range.to);

function applyRange(): void {
    router.get(
        '/admin/report',
        { from: from.value, to: to.value },
        { preserveState: true, preserveScroll: true },
    );
}

function setPreset(days: number): void {
    const end = new Date();
    const start = new Date();
    start.setUTCDate(end.getUTCDate() - days + 1);

    from.value = start.toISOString().slice(0, 10);
    to.value = end.toISOString().slice(0, 10);
    applyRange();
}

function formatDay(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    }).format(new Date(`${date}T00:00:00Z`));
}
</script>

<template>
    <Head title="Admin report" />

    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Operator report
                </h1>
                <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                    Revenue, Pixelforge's provider cost and profit per UTC day
                    and user. Provider costs are reconciled against
                    <code class="rounded bg-muted px-1 py-0.5 text-[11px]"
                        >GET /v1/usage</code
                    >
                    for this environment's API key.
                </p>
            </div>

            <div class="flex flex-wrap items-end gap-2">
                <div class="space-y-1">
                    <Label for="from" class="text-[11px]">From (UTC)</Label>
                    <Input
                        id="from"
                        v-model="from"
                        type="date"
                        class="h-9 w-[150px]"
                    />
                </div>
                <div class="space-y-1">
                    <Label for="to" class="text-[11px]">To (UTC)</Label>
                    <Input
                        id="to"
                        v-model="to"
                        type="date"
                        class="h-9 w-[150px]"
                    />
                </div>
                <Button size="sm" class="h-9" @click="applyRange">
                    <RefreshCw class="size-4" />
                    Apply
                </Button>
                <div class="flex gap-1">
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-9"
                        @click="setPreset(7)"
                        >7d</Button
                    >
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-9"
                        @click="setPreset(30)"
                        >30d</Button
                    >
                </div>
            </div>
        </header>

        <div
            v-if="report.usage_error"
            class="flex items-start gap-3 rounded-lg border border-amber-300/60 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300"
        >
            <AlertTriangle class="mt-0.5 size-4 shrink-0" />
            <div>
                <p class="font-medium">Pixelforge usage unavailable</p>
                <p class="mt-0.5 text-xs opacity-90">
                    {{ report.usage_error }} The numbers below still come from
                    our own records.
                </p>
            </div>
        </div>

        <div
            v-else
            :class="
                cn(
                    'flex items-start gap-3 rounded-lg border p-4 text-sm',
                    report.totals.reconciled
                        ? 'border-emerald-300/60 bg-emerald-50 text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300'
                        : 'border-rose-300/60 bg-rose-50 text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-300',
                )
            "
        >
            <CheckCircle2
                v-if="report.totals.reconciled"
                class="mt-0.5 size-4 shrink-0"
            />
            <AlertTriangle v-else class="mt-0.5 size-4 shrink-0" />
            <div>
                <p class="font-medium">
                    {{
                        report.totals.reconciled
                            ? 'Provider costs match Pixelforge exactly'
                            : 'Provider costs do not match Pixelforge'
                    }}
                </p>
                <p class="mt-0.5 text-xs opacity-90">
                    Our records:
                    {{ report.totals.provider_cost_formatted }} · Pixelforge
                    usage: {{ report.totals.pixelforge_cost_formatted }} ·
                    difference:
                    {{ report.totals.provider_delta_formatted }}
                </p>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card px-4 py-4 shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-medium text-muted-foreground">
                        Users charged
                    </p>
                    <CircleDollarSign class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ report.totals.charged_formatted }}
                </p>
                <p class="text-[11px] text-muted-foreground">
                    {{ report.totals.generations }} successful generations
                </p>
            </div>
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card px-4 py-4 shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-medium text-muted-foreground">
                        Provider cost
                    </p>
                    <ArrowRight class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ report.totals.provider_cost_formatted }}
                </p>
                <p class="text-[11px] text-muted-foreground">
                    Recorded per generation
                </p>
            </div>
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card px-4 py-4 shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-medium text-muted-foreground">
                        Profit
                    </p>
                    <TrendingUp class="size-4 text-muted-foreground" />
                </div>
                <p
                    :class="
                        cn(
                            'mt-1 text-2xl font-semibold tabular-nums',
                            report.totals.profit >= 0
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-rose-600 dark:text-rose-400',
                        )
                    "
                >
                    {{ report.totals.profit_formatted }}
                </p>
                <p class="text-[11px] text-muted-foreground">
                    {{ report.totals.profit_margin_percent }}% margin
                </p>
            </div>
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card px-4 py-4 shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-medium text-muted-foreground">
                        Pixelforge usage
                    </p>
                    <Users class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ report.totals.pixelforge_cost_formatted ?? '—' }}
                </p>
                <p class="text-[11px] text-muted-foreground">
                    Source of truth for provider cost
                </p>
            </div>
        </div>

        <section class="space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-tight">
                    Daily breakdown
                </h2>
                <p class="text-[11px] text-muted-foreground">
                    Billed on the UTC day the generation completed, like
                    Pixelforge.
                </p>
            </div>

            <div
                class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm"
            >
                <details
                    v-for="(day, index) in report.days"
                    :key="day.date"
                    class="group border-b border-sidebar-border/70 last:border-0"
                    :open="
                        index === report.days.length - 1 && day.rows.length > 0
                    "
                >
                    <summary
                        class="flex cursor-pointer list-none flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 text-sm transition-colors hover:bg-muted/40"
                    >
                        <ChevronDown
                            class="size-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-180"
                        />
                        <span class="w-[110px] font-medium">{{
                            formatDay(day.date)
                        }}</span>
                        <span
                            class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-medium"
                            :class="
                                day.reconciled
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300'
                                    : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300'
                            "
                        >
                            {{
                                day.reconciled
                                    ? 'Reconciled'
                                    : 'Check difference'
                            }}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            {{ day.totals.generations }} generations
                        </span>

                        <span
                            class="ml-auto flex flex-wrap items-center gap-x-5 gap-y-1 text-xs"
                        >
                            <span>
                                <span class="text-muted-foreground"
                                    >Charged</span
                                >
                                <span class="ml-1.5 font-medium tabular-nums">{{
                                    day.totals.charged_formatted
                                }}</span>
                            </span>
                            <span>
                                <span class="text-muted-foreground"
                                    >Provider</span
                                >
                                <span class="ml-1.5 font-medium tabular-nums">{{
                                    day.totals.provider_cost_formatted
                                }}</span>
                            </span>
                            <span>
                                <span class="text-muted-foreground"
                                    >Profit</span
                                >
                                <span
                                    class="ml-1.5 font-medium tabular-nums"
                                    :class="
                                        day.totals.profit >= 0
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-rose-600 dark:text-rose-400'
                                    "
                                    >{{ day.totals.profit_formatted }}</span
                                >
                            </span>
                            <span
                                :class="
                                    day.provider_delta === 0
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-amber-600 dark:text-amber-400'
                                "
                            >
                                Δ {{ day.provider_delta_formatted ?? '—' }}
                            </span>
                        </span>
                    </summary>

                    <div class="border-t border-sidebar-border/60 bg-muted/10">
                        <div
                            v-if="day.rows.length === 0"
                            class="px-4 py-4 text-xs text-muted-foreground"
                        >
                            No completed generations. Pixelforge usage this day:
                            {{ day.pixelforge_cost_formatted ?? '—' }}.
                        </div>
                        <div v-else class="overflow-x-auto">
                            <table class="w-full min-w-[720px] text-xs">
                                <thead>
                                    <tr
                                        class="text-left text-[10px] tracking-wide text-muted-foreground uppercase"
                                    >
                                        <th class="px-4 py-2 font-medium">
                                            User
                                        </th>
                                        <th
                                            class="px-4 py-2 text-right font-medium"
                                        >
                                            Generations
                                        </th>
                                        <th
                                            class="px-4 py-2 text-right font-medium"
                                        >
                                            Charged
                                        </th>
                                        <th
                                            class="px-4 py-2 text-right font-medium"
                                        >
                                            Provider
                                        </th>
                                        <th
                                            class="px-4 py-2 text-right font-medium"
                                        >
                                            Profit
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in day.rows"
                                        :key="row.user_id"
                                        class="border-t border-sidebar-border/40"
                                    >
                                        <td class="px-4 py-2.5">
                                            <span class="font-medium">{{
                                                row.user_name
                                            }}</span>
                                            <span
                                                class="ml-1.5 text-muted-foreground"
                                                >{{ row.user_email }}</span
                                            >
                                        </td>
                                        <td
                                            class="px-4 py-2.5 text-right tabular-nums"
                                        >
                                            {{ row.generations }}
                                        </td>
                                        <td
                                            class="px-4 py-2.5 text-right tabular-nums"
                                        >
                                            {{ row.charged_formatted }}
                                        </td>
                                        <td
                                            class="px-4 py-2.5 text-right tabular-nums"
                                        >
                                            {{ row.provider_cost_formatted }}
                                        </td>
                                        <td
                                            class="px-4 py-2.5 text-right tabular-nums"
                                            :class="
                                                row.profit >= 0
                                                    ? 'text-emerald-600 dark:text-emerald-400'
                                                    : 'text-rose-600 dark:text-rose-400'
                                            "
                                        >
                                            {{ row.profit_formatted }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div
                            class="flex flex-wrap gap-x-6 gap-y-1 border-t border-sidebar-border/40 px-4 py-2 text-[11px] text-muted-foreground"
                        >
                            <span>
                                Our provider cost:
                                <span class="font-medium text-foreground">{{
                                    day.totals.provider_cost_formatted
                                }}</span>
                            </span>
                            <span>
                                Pixelforge usage:
                                <span class="font-medium text-foreground">{{
                                    day.pixelforge_cost_formatted ?? '—'
                                }}</span>
                            </span>
                            <span
                                :class="
                                    day.reconciled
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-amber-600 dark:text-amber-400'
                                "
                            >
                                Difference:
                                {{ day.provider_delta_formatted ?? '—' }}
                            </span>
                        </div>
                    </div>
                </details>
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-sm font-semibold tracking-tight">
                Per-user totals
            </h2>
            <div
                class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm"
            >
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[620px] text-sm">
                        <thead>
                            <tr
                                class="border-b border-sidebar-border/70 text-left text-[10px] tracking-wide text-muted-foreground uppercase"
                            >
                                <th class="px-4 py-2.5 font-medium">User</th>
                                <th class="px-4 py-2.5 text-right font-medium">
                                    Generations
                                </th>
                                <th class="px-4 py-2.5 text-right font-medium">
                                    Charged
                                </th>
                                <th class="px-4 py-2.5 text-right font-medium">
                                    Provider
                                </th>
                                <th class="px-4 py-2.5 text-right font-medium">
                                    Profit
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in report.user_totals"
                                :key="row.user_id"
                                class="border-b border-sidebar-border/40 last:border-0"
                            >
                                <td class="px-4 py-3">
                                    <span class="font-medium">{{
                                        row.user_name
                                    }}</span>
                                    <span
                                        class="ml-1.5 text-xs text-muted-foreground"
                                        >{{ row.user_email }}</span
                                    >
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    {{ row.generations }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    {{ row.charged_formatted }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    {{ row.provider_cost_formatted }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right tabular-nums"
                                    :class="
                                        row.profit >= 0
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-rose-600 dark:text-rose-400'
                                    "
                                >
                                    {{ row.profit_formatted }}
                                </td>
                            </tr>
                            <tr
                                v-if="report.user_totals.length === 0"
                                class="text-sm text-muted-foreground"
                            >
                                <td colspan="5" class="px-4 py-8 text-center">
                                    No completed generations in this range.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</template>
