<?php

namespace App\Services;

use App\Models\FinancialPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Get aggregate financial metrics and summary cards for a user and period.
     */
    public function getSummary(User $user, ?FinancialPeriod $period = null): array
    {
        // 1. Total Saldo Likuid (all active accounts or period closing balance if closed)
        if ($period && $period->isClosed() && $period->total_closing_balance !== null) {
            $totalLiquidBalance = (float) $period->total_closing_balance;
        } else {
            $totalLiquidBalance = (float) $user->accounts()
                ->where('is_active', true)
                ->sum('current_balance');
        }

        // 2. Total Piutang Aktif (unpaid/partially_paid receivables)
        $totalReceivables = (float) $user->debts()
            ->where('type', 'receivable')
            ->where('status', '!=', 'paid')
            ->sum('remaining_amount');

        // 3. Total Utang Aktif (unpaid/partially_paid debts)
        $totalDebts = (float) $user->debts()
            ->where('type', 'debt')
            ->where('status', '!=', 'paid')
            ->sum('remaining_amount');

        // 4. Net Worth = (Total Saldo + Total Piutang) - Total Utang
        $netWorth = ($totalLiquidBalance + $totalReceivables) - $totalDebts;

        // Period income & expenses
        if ($period) {
            $monthIncome = (float) $period->total_income;
            $monthExpense = (float) $period->total_expense;
            $netSavings = (float) $period->net_savings;
        } else {
            $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
            $endOfMonth = Carbon::now()->endOfMonth()->toDateString();

            $monthIncome = (float) $user->transactions()
                ->where('type', 'income')
                ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
                ->sum('amount');

            $monthExpense = (float) $user->transactions()
                ->where('type', 'expense')
                ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
                ->sum('amount');

            $netSavings = $monthIncome - $monthExpense;
        }

        return [
            'total_liquid_balance' => $totalLiquidBalance,
            'total_receivables' => $totalReceivables,
            'total_debts' => $totalDebts,
            'net_worth' => $netWorth,
            'month_income' => $monthIncome,
            'month_expense' => $monthExpense,
            'net_savings' => $netSavings,
            'opening_balance' => $period ? (float) $period->total_opening_balance : null,
            'closing_balance' => $period ? (float) $period->total_closing_balance : null,
            'period_status' => $period ? $period->status : 'open',
            'period_name' => $period ? $period->name : Carbon::now()->translatedFormat('F Y'),
            'currency_symbol' => $user->currency_symbol ?? 'Rp',
        ];
    }

    /**
     * Get monthly cash flow (income vs expense) for the last 6 months.
     */
    public function getMonthlyCashFlow(User $user): array
    {
        $months = [];
        $labels = [];
        $incomeData = [];
        $expenseData = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $key = $date->format('Y-m');
            $label = $date->translatedFormat('M Y');
            $labels[] = $label;
            $months[$key] = [
                'income' => 0,
                'expense' => 0,
            ];
        }

        $startDate = Carbon::now()->subMonths(5)->startOfMonth()->toDateString();
        $endDate = Carbon::now()->endOfMonth()->toDateString();

        $transactions = $user->transactions()
            ->whereIn('type', ['income', 'expense'])
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->get();

        foreach ($transactions as $tx) {
            $monthKey = Carbon::parse($tx->transaction_date)->format('Y-m');
            if (isset($months[$monthKey])) {
                if ($tx->type === 'income') {
                    $months[$monthKey]['income'] += (float) $tx->amount;
                } elseif ($tx->type === 'expense') {
                    $months[$monthKey]['expense'] += (float) $tx->amount;
                }
            }
        }

        foreach ($months as $item) {
            $incomeData[] = round($item['income'], 2);
            $expenseData[] = round($item['expense'], 2);
        }

        return [
            'labels' => $labels,
            'income' => $incomeData,
            'expense' => $expenseData,
        ];
    }

    /**
     * Get expense breakdown by category for a period.
     */
    public function getExpenseByCategory(User $user, ?FinancialPeriod $period = null): array
    {
        $query = $user->transactions()->where('transactions.type', 'expense');

        if ($period) {
            $query->where(function ($q) use ($period) {
                $q->where('financial_period_id', $period->id)
                  ->orWhereBetween('transaction_date', [$period->start_date->toDateString(), $period->end_date->toDateString()]);
            });
        } else {
            $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
            $endOfMonth = Carbon::now()->endOfMonth()->toDateString();
            $query->whereBetween('transaction_date', [$startOfMonth, $endOfMonth]);
        }

        $expenses = $query->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
            ->select(
                DB::raw("COALESCE(categories.name, 'Lain-lain') as category_name"),
                DB::raw("COALESCE(categories.color_hex, '#9CA3AF') as color_hex"),
                DB::raw('SUM(transactions.amount) as total_amount')
            )
            ->groupBy('category_name', 'color_hex')
            ->orderByDesc('total_amount')
            ->get();

        $labels = [];
        $data = [];
        $colors = [];

        foreach ($expenses as $item) {
            $labels[] = $item->category_name;
            $data[] = (float) $item->total_amount;
            $colors[] = $item->color_hex;
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'colors' => $colors,
        ];
    }

    /**
     * Get recent transactions for a period.
     */
    public function getRecentTransactions(User $user, ?FinancialPeriod $period = null, int $limit = 7)
    {
        $query = $user->transactions()
            ->with(['account', 'toAccount', 'category'])
            ->latest('transaction_date')
            ->latest('id');

        if ($period) {
            $query->where(function ($q) use ($period) {
                $q->where('financial_period_id', $period->id)
                  ->orWhereBetween('transaction_date', [$period->start_date->toDateString(), $period->end_date->toDateString()]);
            });
        }

        return $query->limit($limit)->get();
    }

    /**
     * Get upcoming or overdue debts and receivables.
     */
    public function getUpcomingDebts(User $user, int $limit = 5)
    {
        return $user->debts()
            ->where('status', '!=', 'paid')
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, due_date ASC')
            ->limit($limit)
            ->get();
    }
}
