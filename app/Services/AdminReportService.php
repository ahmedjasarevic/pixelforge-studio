<?php

namespace App\Services;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Pixelforge\PixelforgeClient;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Operator report: what users were charged, what Pixelforge charged us, and
 * profit. Provider costs are grouped by the UTC day the generation *completed*,
 * exactly like GET /v1/usage, and the report fetches that endpoint so the two
 * numbers can be reconciled on screen.
 */
class AdminReportService
{
    public function __construct(private readonly PixelforgeClient $client) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Carbon $from, Carbon $to): array
    {
        $generations = Generation::query()
            ->with('user')
            ->where('status', GenerationStatus::Succeeded)
            ->whereNotNull('settled_at')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$from->copy()->utc()->startOfDay(), $to->copy()->utc()->endOfDay()])
            ->get();

        $byDate = $generations->groupBy(
            fn (Generation $generation) => $generation->completed_at->utc()->toDateString()
        );

        [$usageByDate, $usageTotal, $usageError] = $this->fetchUsage($from, $to);

        $days = [];
        $userTotals = [];
        $totals = $this->emptyTotals();
        $providerDeltaTotal = 0;
        $providerDeltaComparable = true;

        $cursor = $from->copy()->utc()->startOfDay();
        $lastDay = $to->copy()->utc()->startOfDay();

        while ($cursor->lte($lastDay)) {
            $date = $cursor->toDateString();
            $dayGenerations = collect($byDate->get($date, []));
            $dayTotals = $this->emptyTotals();
            $rows = [];

            foreach ($dayGenerations->groupBy('user_id') as $userId => $group) {
                /** @var Generation $first */
                $first = $group->first();
                $user = $first->user;

                $charged = (int) $group->sum('charged_amount');
                $providerCost = (int) $group->sum('provider_cost');
                $profit = $charged - $providerCost;

                $rows[] = [
                    'user_id' => (int) $userId,
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'generations' => $group->count(),
                    'charged' => $charged,
                    'charged_formatted' => $this->money($charged),
                    'provider_cost' => $providerCost,
                    'provider_cost_formatted' => $this->money($providerCost),
                    'profit' => $profit,
                    'profit_formatted' => $this->money($profit),
                ];

                $dayTotals['generations'] += $group->count();
                $dayTotals['charged'] += $charged;
                $dayTotals['provider_cost'] += $providerCost;

                $userTotals[$userId] ??= [
                    'user_id' => (int) $userId,
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'generations' => 0,
                    'charged' => 0,
                    'provider_cost' => 0,
                ];

                $userTotals[$userId]['generations'] += $group->count();
                $userTotals[$userId]['charged'] += $charged;
                $userTotals[$userId]['provider_cost'] += $providerCost;
            }

            usort($rows, fn (array $a, array $b) => strcmp($a['user_name'], $b['user_name']));

            $dayTotals['profit'] = $dayTotals['charged'] - $dayTotals['provider_cost'];
            $usageCost = $usageByDate[$date] ?? null;
            $delta = $usageCost !== null ? $dayTotals['provider_cost'] - $usageCost : null;

            if ($delta !== null) {
                $providerDeltaTotal += $delta;
            } else {
                $providerDeltaComparable = false;
            }

            $days[] = [
                'date' => $date,
                'rows' => $rows,
                'totals' => $this->formatTotals($dayTotals),
                'pixelforge_cost' => $usageCost,
                'pixelforge_cost_formatted' => $usageCost !== null ? $this->money($usageCost) : null,
                'provider_delta' => $delta,
                'provider_delta_formatted' => $delta !== null ? $this->money($delta) : null,
                'reconciled' => $delta !== null && $delta === 0,
            ];

            $totals['generations'] += $dayTotals['generations'];
            $totals['charged'] += $dayTotals['charged'];
            $totals['provider_cost'] += $dayTotals['provider_cost'];

            $cursor->addDay();
        }

        $totals['profit'] = $totals['charged'] - $totals['provider_cost'];

        $userTotals = array_values(array_map(function (array $row) {
            $row['profit'] = $row['charged'] - $row['provider_cost'];
            $row['charged_formatted'] = $this->money($row['charged']);
            $row['provider_cost_formatted'] = $this->money($row['provider_cost']);
            $row['profit_formatted'] = $this->money($row['profit']);

            return $row;
        }, $userTotals));

        usort($userTotals, fn (array $a, array $b) => strcmp($a['user_name'], $b['user_name']));

        $reconciled = $usageError === null && $providerDeltaComparable && $providerDeltaTotal === 0;

        return [
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'days' => $days,
            'user_totals' => $userTotals,
            'totals' => [
                ...$this->formatTotals($totals),
                'pixelforge_cost' => $usageTotal,
                'pixelforge_cost_formatted' => $usageTotal !== null ? $this->money($usageTotal) : null,
                'provider_delta' => $usageTotal !== null ? $totals['provider_cost'] - $usageTotal : null,
                'provider_delta_formatted' => $usageTotal !== null ? $this->money($totals['provider_cost'] - $usageTotal) : null,
                'profit_margin_percent' => $totals['charged'] > 0
                    ? round($totals['profit'] / $totals['charged'] * 100, 1)
                    : 0.0,
                'reconciled' => $reconciled,
            ],
            'usage_error' => $usageError,
        ];
    }

    /**
     * @return array{0: array<string, int>, 1: int|null, 2: string|null}
     */
    private function fetchUsage(Carbon $from, Carbon $to): array
    {
        try {
            $usage = $this->client->usage($from->copy()->utc()->startOfDay(), $to->copy()->utc()->startOfDay());

            $byDate = [];

            foreach ($usage['data'] ?? [] as $row) {
                if (is_array($row) && isset($row['date'], $row['cost_usd'])) {
                    $byDate[(string) $row['date']] = Money::fromUsd((string) $row['cost_usd']);
                }
            }

            $total = isset($usage['total']['cost_usd'])
                ? Money::fromUsd((string) $usage['total']['cost_usd'])
                : array_sum($byDate);

            return [$byDate, $total, null];
        } catch (Throwable $exception) {
            report($exception);

            return [[], null, 'Could not load Pixelforge usage: '.$exception->getMessage()];
        }
    }

    /**
     * @return array{generations: int, charged: int, provider_cost: int, profit: int}
     */
    private function emptyTotals(): array
    {
        return ['generations' => 0, 'charged' => 0, 'provider_cost' => 0, 'profit' => 0];
    }

    /**
     * @param  array{generations: int, charged: int, provider_cost: int, profit: int}  $totals
     * @return array<string, mixed>
     */
    private function formatTotals(array $totals): array
    {
        return [
            'generations' => $totals['generations'],
            'charged' => $totals['charged'],
            'charged_formatted' => $this->money($totals['charged']),
            'provider_cost' => $totals['provider_cost'],
            'provider_cost_formatted' => $this->money($totals['provider_cost']),
            'profit' => $totals['profit'],
            'profit_formatted' => $this->money($totals['profit']),
        ];
    }

    private function money(int $picodollars): string
    {
        return Money::format($picodollars, 9);
    }
}
