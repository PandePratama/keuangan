<?php

namespace App\Services;

use App\Models\FinancialPeriod;
use App\Models\PeriodAccountBalance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PeriodService
{
    protected array $monthNames = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    public function __construct(
        protected AccountService $accountService
    ) {}

    /**
     * Get or create a financial period for a given user and date.
     */
    public function getOrCreatePeriodForDate(User $user, ?Carbon $date = null): FinancialPeriod
    {
        $date = $date ?: Carbon::now();
        $year = (int) $date->year;
        $month = (int) $date->month;

        $period = $user->periods()->where('year', $year)->where('month', $month)->first();

        if ($period) {
            return $period;
        }

        return DB::transaction(function () use ($user, $year, $month, $date) {
            $monthName = $this->monthNames[$month] ?? $date->format('F');
            $periodName = "{$monthName} {$year}";

            $startDate = $date->copy()->startOfMonth()->toDateString();
            $endDate = $date->copy()->endOfMonth()->toDateString();

            // Find previous period to carry over closing balance
            $previousPeriod = $user->periods()
                ->where(function ($q) use ($year, $month) {
                    $q->where('year', '<', $year)
                      ->orWhere(function ($q2) use ($year, $month) {
                          $q2->where('year', $year)->where('month', '<', $month);
                      });
                })
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->first();

            $openingBalance = 0;

            $period = FinancialPeriod::create([
                'user_id' => $user->id,
                'name' => $periodName,
                'year' => $year,
                'month' => $month,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'open',
                'total_opening_balance' => 0,
                'total_income' => 0,
                'total_expense' => 0,
                'net_savings' => 0,
            ]);

            $accounts = $user->accounts()->active()->get();

            foreach ($accounts as $account) {
                $accOpening = 0;
                if ($previousPeriod) {
                    $prevAccBalance = PeriodAccountBalance::where('financial_period_id', $previousPeriod->id)
                        ->where('account_id', $account->id)
                        ->first();
                    $accOpening = $prevAccBalance?->closing_balance !== null
                        ? (float) $prevAccBalance->closing_balance
                        : (float) $account->current_balance;
                } else {
                    $accOpening = (float) $account->current_balance;
                }

                $openingBalance += $accOpening;

                PeriodAccountBalance::create([
                    'financial_period_id' => $period->id,
                    'account_id' => $account->id,
                    'opening_balance' => $accOpening,
                ]);
            }

            $period->total_opening_balance = $openingBalance;
            $period->save();

            return $period;
        });
    }

    /**
     * Get currently active or viewed period for user.
     */
    public function getActivePeriod(User $user, ?int $periodId = null): FinancialPeriod
    {
        if ($periodId) {
            $period = $user->periods()->where('id', $periodId)->first();
            if ($period) {
                return $period;
            }
        }

        // Return latest open period, or current month period
        $openPeriod = $user->periods()->open()->orderBy('year', 'desc')->orderBy('month', 'desc')->first();
        if ($openPeriod) {
            return $openPeriod;
        }

        return $this->getOrCreatePeriodForDate($user, Carbon::now());
    }

    /**
     * Execute monthly book closing (Tutup Buku Bulanan).
     */
    public function closePeriod(FinancialPeriod $period, array $actualBalances = [], ?string $notes = null): FinancialPeriod
    {
        if ($period->isClosed()) {
            throw new InvalidArgumentException('Buku periode ini sudah ditutup sebelumnya.');
        }

        return DB::transaction(function () use ($period, $actualBalances, $notes) {
            $user = $period->user;

            // 1. If user provided reconciliation balances at closing, adjust accounts atomically
            if (! empty($actualBalances)) {
                $this->accountService->reconcileBulk(
                    $user,
                    $actualBalances,
                    $notes ?: ("Penyesuaian saat tutup buku {$period->name}")
                );
            }

            // 2. Calculate period transactions total income, expense, and net savings
            $totalIncome = (float) $period->transactions()->where('type', 'income')->sum('amount');
            $totalExpense = (float) $period->transactions()->where('type', 'expense')->sum('amount');
            $netSavings = $totalIncome - $totalExpense;

            // 3. Snapshot closing balances for each active account
            $totalClosingBalance = 0;
            $accounts = $user->accounts()->active()->get();

            foreach ($accounts as $account) {
                $closing = (float) $account->current_balance;
                $totalClosingBalance += $closing;

                PeriodAccountBalance::updateOrCreate(
                    [
                        'financial_period_id' => $period->id,
                        'account_id' => $account->id,
                    ],
                    [
                        'closing_balance' => $closing,
                    ]
                );
            }

            // 4. Mark this period as closed
            $period->update([
                'status' => 'closed',
                'total_closing_balance' => $totalClosingBalance,
                'total_income' => $totalIncome,
                'total_expense' => $totalExpense,
                'net_savings' => $netSavings,
                'closed_at' => Carbon::now(),
            ]);

            // 5. Automatically initialize next month's open book with carried-over opening balances
            $nextMonthDate = Carbon::parse($period->start_date)->addMonth();
            $this->getOrCreatePeriodForDate($user, $nextMonthDate);

            return $period;
        });
    }

    /**
     * Reopen a closed period (Buka Kunci Sementara).
     */
    public function reopenPeriod(FinancialPeriod $period): FinancialPeriod
    {
        $period->update([
            'status' => 'open',
            'closed_at' => null,
        ]);

        return $period;
    }

    /**
     * Recalculate financial metrics for a period.
     */
    public function recalculateMetrics(FinancialPeriod $period): void
    {
        $totalIncome = (float) $period->transactions()->where('type', 'income')->sum('amount');
        $totalExpense = (float) $period->transactions()->where('type', 'expense')->sum('amount');

        $period->update([
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_savings' => $totalIncome - $totalExpense,
        ]);
    }
}
