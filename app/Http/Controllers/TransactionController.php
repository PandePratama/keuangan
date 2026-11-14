<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\PeriodService;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function __construct(
        protected TransactionService $transactionService,
        protected PeriodService $periodService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['type', 'account_id', 'category_id', 'start_date', 'end_date', 'search', 'period_id']);

        $activePeriodId = $request->session()->get('active_period_id');
        $activePeriod = $this->periodService->getActivePeriod($user, $activePeriodId);

        // Default to active period if no custom date range is set
        if (empty($filters['start_date']) && empty($filters['end_date']) && empty($filters['period_id'])) {
            $filters['period_id'] = $activePeriod->id;
        }

        $transactions = $user->transactions()
            ->with(['account', 'toAccount', 'category', 'financialPeriod'])
            ->filter($filters)
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $accounts = $user->accounts()->active()->get();
        $categories = Category::availableForUser($user->id)->get();

        // Calculate summary for filtered period
        $filteredIncome = (float) (clone $user->transactions()->filter($filters))->where('type', 'income')->sum('amount');
        $filteredExpense = (float) (clone $user->transactions()->filter($filters))->where('type', 'expense')->sum('amount');

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'filters' => $filters,
            'activePeriod' => $activePeriod,
            'accounts' => $accounts,
            'categories' => $categories,
            'stats' => [
                'income' => $filteredIncome,
                'expense' => $filteredExpense,
                'net' => $filteredIncome - $filteredExpense,
            ],
            'currencySymbol' => $user->currency_symbol ?? 'Rp',
        ]);
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        try {
            $this->transactionService->createTransaction($request->user(), $request->validated());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['transaction_date' => $e->getMessage()]);
        }

        return back()->with('success', 'Transaksi berhasil disimpan.');
    }

    public function destroy(Request $request, Transaction $transaction): RedirectResponse
    {
        Gate::authorize('delete', $transaction);

        try {
            $this->transactionService->deleteTransaction($transaction);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transaksi berhasil dihapus dan saldo disesuaikan.');
    }
}
