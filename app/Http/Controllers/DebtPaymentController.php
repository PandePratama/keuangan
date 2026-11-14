<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDebtPaymentRequest;
use App\Models\Debt;
use App\Services\DebtService;
use Illuminate\Http\RedirectResponse;

class DebtPaymentController extends Controller
{
    public function __construct(
        protected DebtService $debtService
    ) {}

    public function store(StoreDebtPaymentRequest $request, Debt $debt): RedirectResponse
    {
        $this->debtService->recordPayment($debt, $request->validated());

        $message = $debt->type === 'debt'
            ? 'Pembayaran cicilan utang berhasil dicatat.'
            : 'Penerimaan cicilan piutang berhasil dicatat.';

        return back()->with('success', $message);
    }
}
