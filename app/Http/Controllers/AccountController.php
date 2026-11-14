<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdjustAccountBalanceRequest;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(
        protected AccountService $accountService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $accounts = $user->accounts()->latest()->get();

        $totalBalance = (float) $accounts->where('is_active', true)->sum('current_balance');
        $bankBalance = (float) $accounts->where('is_active', true)->where('type', 'bank')->sum('current_balance');
        $ewalletBalance = (float) $accounts->where('is_active', true)->where('type', 'ewallet')->sum('current_balance');
        $cashBalance = (float) $accounts->where('is_active', true)->where('type', 'cash')->sum('current_balance');

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
            'metrics' => [
                'total' => $totalBalance,
                'bank' => $bankBalance,
                'ewallet' => $ewalletBalance,
                'cash' => $cashBalance,
            ],
            'currencySymbol' => $user->currency_symbol ?? 'Rp',
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $this->accountService->createAccount($request->user(), $request->validated());

        return back()->with('success', 'Rekening/Dompet berhasil ditambahkan.');
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $this->accountService->updateAccount($account, $request->validated());

        return back()->with('success', 'Rekening/Dompet berhasil diperbarui.');
    }

    public function adjustBalance(AdjustAccountBalanceRequest $request, Account $account): RedirectResponse
    {
        $this->accountService->adjustBalance(
            $account,
            (float) $request->validated('actual_balance'),
            $request->validated('notes')
        );

        return back()->with('success', 'Saldo rekening berhasil direkonsiliasi.');
    }

    public function reconcileBulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'adjustments' => ['required', 'array'],
            'adjustments.*.account_id' => ['required', 'exists:accounts,id'],
            'adjustments.*.actual_balance' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $this->accountService->reconcileBulk(
            $request->user(),
            $validated['adjustments'],
            $validated['notes'] ?? null
        );

        return back()->with('success', 'Semua rekening berhasil direkonsiliasi secara massal.');
    }

    public function toggleActive(Request $request, Account $account): RedirectResponse
    {
        Gate::authorize('update', $account);
        $this->accountService->toggleActive($account);

        return back()->with('success', 'Status rekening berhasil diubah.');
    }

    public function destroy(Request $request, Account $account): RedirectResponse
    {
        Gate::authorize('delete', $account);
        $this->accountService->deleteAccount($account);

        return back()->with('success', 'Rekening berhasil dihapus.');
    }
}
