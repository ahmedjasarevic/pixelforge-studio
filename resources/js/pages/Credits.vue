<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowDownLeft,
    ArrowUpRight,
    Info,
    LockKeyhole,
    Wallet,
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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import type { CreditTransaction } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Credits', href: '/credits' }],
    },
});

const props = defineProps<{
    transactions: CreditTransaction[];
    purchaseOptions: number[];
}>();

const page = usePage();
const form = useForm({ amount: '10.00' });

const balance = computed(() => page.props.wallet?.balance ?? '$0.00');

const typeLabels: Record<CreditTransaction['type'], string> = {
    purchase: 'Purchase',
    hold: 'Hold',
    settlement: 'Settlement',
};

const typeClasses: Record<CreditTransaction['type'], string> = {
    purchase:
        'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300',
    hold: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300',
    settlement:
        'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/60 dark:bg-sky-950/40 dark:text-sky-300',
};

function purchase(): void {
    form.post('/credits/purchase', {
        preserveScroll: true,
    });
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}
</script>

<template>
    <Head title="Credits" />

    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">Credits</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Credits are US dollars. You pay Pixelforge's provider cost plus
                a 25% platform fee for every successful generation.
            </p>
        </header>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
            <div class="space-y-5">
                <div
                    class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-violet-600 via-violet-600 to-fuchsia-600 p-6 text-white shadow-lg shadow-violet-500/20"
                >
                    <div
                        class="absolute -top-16 -right-12 size-48 rounded-full bg-white/10 blur-2xl"
                    />
                    <div class="relative flex items-start justify-between">
                        <div>
                            <p
                                class="text-xs font-medium tracking-wide text-white/75 uppercase"
                            >
                                Available balance
                            </p>
                            <p
                                class="mt-1 text-4xl font-semibold tracking-tight tabular-nums"
                            >
                                {{ balance }}
                            </p>
                        </div>
                        <div
                            class="flex size-11 items-center justify-center rounded-xl bg-white/15 backdrop-blur"
                        >
                            <Wallet class="size-5" />
                        </div>
                    </div>
                    <p class="relative mt-4 max-w-md text-xs text-white/75">
                        Credits reserved for a running generation are already
                        deducted. Unused hold amounts are returned the moment it
                        finishes.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Credit ledger</CardTitle>
                        <CardDescription>
                            Every movement, including temporary holds and final
                            settlements.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="transactions.length === 0"
                            class="py-10 text-center text-sm text-muted-foreground"
                        >
                            No credit activity yet.
                        </div>

                        <div v-else class="-mx-2 overflow-x-auto">
                            <table class="w-full min-w-[620px] text-sm">
                                <thead>
                                    <tr
                                        class="border-b border-sidebar-border/70 text-left text-[11px] tracking-wide text-muted-foreground uppercase"
                                    >
                                        <th class="px-2 py-2 font-medium">
                                            When
                                        </th>
                                        <th class="px-2 py-2 font-medium">
                                            Description
                                        </th>
                                        <th class="px-2 py-2 font-medium">
                                            Type
                                        </th>
                                        <th
                                            class="px-2 py-2 text-right font-medium"
                                        >
                                            Amount
                                        </th>
                                        <th
                                            class="px-2 py-2 text-right font-medium"
                                        >
                                            Balance
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="transaction in transactions"
                                        :key="transaction.id"
                                        class="border-b border-sidebar-border/40 last:border-0"
                                    >
                                        <td
                                            class="px-2 py-3 text-xs whitespace-nowrap text-muted-foreground"
                                        >
                                            {{
                                                formatDate(
                                                    transaction.created_at,
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="max-w-[260px] truncate px-2 py-3"
                                            :title="
                                                transaction.description ?? ''
                                            "
                                        >
                                            {{ transaction.description }}
                                        </td>
                                        <td class="px-2 py-3">
                                            <span
                                                :class="[
                                                    'inline-flex rounded-full border px-2 py-0.5 text-[10px] font-medium',
                                                    typeClasses[
                                                        transaction.type
                                                    ],
                                                ]"
                                            >
                                                {{
                                                    typeLabels[transaction.type]
                                                }}
                                            </span>
                                        </td>
                                        <td
                                            :class="
                                                cn(
                                                    'px-2 py-3 text-right font-medium whitespace-nowrap tabular-nums',
                                                    transaction.amount >= 0
                                                        ? 'text-emerald-600 dark:text-emerald-400'
                                                        : 'text-muted-foreground',
                                                )
                                            "
                                        >
                                            {{ transaction.amount_formatted }}
                                        </td>
                                        <td
                                            class="px-2 py-3 text-right whitespace-nowrap tabular-nums"
                                        >
                                            {{
                                                transaction.balance_after_formatted
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <aside class="space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Buy credits</CardTitle>
                        <CardDescription>
                            Add demo credits instantly. No real payment is
                            taken.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-2 gap-2">
                            <Button
                                v-for="amount in purchaseOptions"
                                :key="amount"
                                type="button"
                                variant="outline"
                                :class="
                                    cn(
                                        'h-12 text-base font-semibold',
                                        Number(form.amount) === amount &&
                                            'border-violet-500 ring-2 ring-violet-500/20 dark:border-violet-400',
                                    )
                                "
                                @click="form.amount = amount.toFixed(2)"
                            >
                                ${{ amount }}
                            </Button>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="amount">Or a custom amount</Label>
                            <div class="relative">
                                <span
                                    class="absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                                    >$</span
                                >
                                <Input
                                    id="amount"
                                    v-model="form.amount"
                                    type="text"
                                    inputmode="decimal"
                                    class="pl-7"
                                    placeholder="10.00"
                                />
                            </div>
                            <InputError :message="form.errors.amount" />
                            <p class="text-[11px] text-muted-foreground">
                                $1.00 – $1,000.00 per fake purchase.
                            </p>
                        </div>
                    </CardContent>
                    <CardFooter>
                        <Button
                            type="button"
                            class="w-full"
                            :disabled="form.processing"
                            @click="purchase"
                        >
                            <Spinner v-if="form.processing" />
                            <ArrowDownLeft v-else class="size-4" />
                            Add
                            {{ form.amount ? `$${form.amount}` : 'credits' }}
                        </Button>
                    </CardFooter>
                </Card>

                <Card class="border-sidebar-border/70 bg-muted/30 shadow-none">
                    <CardContent
                        class="flex items-start gap-3 pt-4 text-xs text-muted-foreground"
                    >
                        <LockKeyhole class="mt-0.5 size-4 shrink-0" />
                        <div class="space-y-1.5">
                            <p class="font-medium text-foreground">
                                How the money works
                            </p>
                            <p class="flex items-start gap-1.5">
                                <ArrowUpRight class="mt-0.5 size-3 shrink-0" />
                                A hold is placed for the maximum possible cost
                                when you submit.
                            </p>
                            <p class="flex items-start gap-1.5">
                                <ArrowDownLeft class="mt-0.5 size-3 shrink-0" />
                                When the generation finishes, the hold is
                                replaced by the exact cost and the difference is
                                refunded.
                            </p>
                            <p class="flex items-start gap-1.5">
                                <Info class="mt-0.5 size-3 shrink-0" />
                                Failed generations release the full hold.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </aside>
        </div>
    </div>
</template>
