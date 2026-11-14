<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDebtRequest;
use App\Models\Debt;
use App\Services\DebtService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DebtController extends Controller
{
    public function __construct(
        protected DebtService $debtService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $type = $request->query('type', 'debt'); // default to 'debt' (Utang Saya)

        $debts = $user->debts()
            ->where('type', $type)
            ->with(['payments.transaction.account'])
            ->orderByRaw("CASE WHEN status = 'paid' THEN 2 WHEN due_date IS NOT NULL AND due_date < CURRENT_DATE THEN 0 ELSE 1 END")
            ->orderBy('due_date', 'asc')
            ->latest('id')
            ->get();

        $accounts = $user->accounts()->active()->get();

        // Metrics for both debts and receivables
        $totalDebts = (float) $user->debts()->where('type', 'debt')->where('status', '!=', 'paid')->sum('remaining_amount');
        $totalReceivables = (float) $user->debts()->where('type', 'receivable')->where('status', '!=', 'paid')->sum('remaining_amount');
        $overdueDebts = $user->debts()->where('type', 'debt')->where('status', '!=', 'paid')->whereNotNull('due_date')->whereDate('due_date', '<', now()->toDateString())->count();
        $overdueReceivables = $user->debts()->where('type', 'receivable')->where('status', '!=', 'paid')->whereNotNull('due_date')->whereDate('due_date', '<', now()->toDateString())->count();

        return Inertia::render('Debts/Index', [
            'debts' => $debts,
            'currentType' => $type,
            'accounts' => $accounts,
            'metrics' => [
                'total_debts' => $totalDebts,
                'total_receivables' => $totalReceivables,
                'overdue_debts' => $overdueDebts,
                'overdue_receivables' => $overdueReceivables,
            ],
            'currencySymbol' => $user->currency_symbol ?? 'Rp',
        ]);
    }

    public function store(StoreDebtRequest $request): RedirectResponse
    {
        $this->debtService->createDebt($request->user(), $request->validated());

        return back()->with('success', 'Catatan berhasil dibuat.');
    }

    public function writeOff(Request $request, Debt $debt): RedirectResponse
    {
        Gate::authorize('update', $debt);
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $this->debtService->writeOff($debt, $request->input('reason'));

        return back()->with('success', 'Sisa tagihan berhasil diikhlaskan dan ditandai lunas.');
    }

    public function destroy(Request $request, Debt $debt): RedirectResponse
    {
        Gate::authorize('delete', $debt);
        $this->debtService->deleteDebt($debt);

        return back()->with('success', 'Catatan berhasil dihapus.');
    }
}
