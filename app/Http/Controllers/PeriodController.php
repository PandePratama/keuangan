<?php

namespace App\Http\Controllers;

use App\Models\FinancialPeriod;
use App\Services\PeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PeriodController extends Controller
{
    public function __construct(
        protected PeriodService $periodService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $periods = $user->periods()
            ->with(['periodAccountBalances.account'])
            ->get();

        $activePeriodId = $request->session()->get('active_period_id');
        $activePeriod = $this->periodService->getActivePeriod($user, $activePeriodId);

        return Inertia::render('Periods/Index', [
            'periods' => $periods,
            'activePeriod' => $activePeriod,
            'accounts' => $user->accounts()->active()->get(),
            'currencySymbol' => $user->currency_symbol ?? 'Rp',
        ]);
    }

    public function switch(Request $request, FinancialPeriod $period): RedirectResponse
    {
        if ($period->user_id !== $request->user()->id) {
            abort(403);
        }

        $request->session()->put('active_period_id', $period->id);

        return back()->with('success', "Buku aktif dialihkan ke periode {$period->name}.");
    }

    public function close(Request $request, FinancialPeriod $period): RedirectResponse
    {
        if ($period->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'actual_balances' => ['nullable', 'array'],
            'actual_balances.*.account_id' => ['required', 'exists:accounts,id'],
            'actual_balances.*.actual_balance' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $this->periodService->closePeriod(
            $period,
            $validated['actual_balances'] ?? [],
            $validated['notes'] ?? null
        );

        // Switch to the newly opened next period
        $nextPeriod = $this->periodService->getActivePeriod($request->user());
        $request->session()->put('active_period_id', $nextPeriod->id);

        return back()->with('success', "Buku periode {$period->name} berhasil ditutup (Closing). Buku periode baru telah dibuka.");
    }

    public function reopen(Request $request, FinancialPeriod $period): RedirectResponse
    {
        if ($period->user_id !== $request->user()->id) {
            abort(403);
        }

        $this->periodService->reopenPeriod($period);
        $request->session()->put('active_period_id', $period->id);

        return back()->with('success', "Kunci buku periode {$period->name} berhasil dibuka kembali (Reopened).");
    }
}
