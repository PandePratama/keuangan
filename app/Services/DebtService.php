<?php

namespace App\Services;

use App\Events\DebtStatusChanged;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class DebtService
{
    public function __construct(
        protected TransactionService $transactionService
    ) {}

    /**
     * Create a debt or receivable record.
     */
    public function createDebt(User $user, array $data): Debt
    {
        return DB::transaction(function () use ($user, $data) {
            $totalAmount = (float) $data['total_amount'];
            if ($totalAmount <= 0) {
                throw new InvalidArgumentException('Total nominal harus lebih dari 0.');
            }

            $type = $data['type']; // 'debt' or 'receivable'

            $debt = $user->debts()->create([
                'party_name' => $data['party_name'],
                'type' => $type,
                'total_amount' => $totalAmount,
                'remaining_amount' => $totalAmount,
                'due_date' => $data['due_date'] ?? null,
                'status' => 'unpaid',
                'notes' => $data['notes'] ?? null,
            ]);

            // If user checked "Disburse immediately to / from account"
            if (! empty($data['account_id'])) {
                $accountId = $data['account_id'];

                $txType = $type === 'debt' ? 'income' : 'expense';
                $notesPrefix = $type === 'debt'
                    ? "Pencairan pinjaman/utang dari: {$debt->party_name}"
                    : "Pemberian pinjaman/piutang kepada: {$debt->party_name}";

                $this->transactionService->createTransaction($user, [
                    'account_id' => $accountId,
                    'type' => $txType,
                    'amount' => $totalAmount,
                    'transaction_date' => now()->toDateString(),
                    'notes' => $notesPrefix . ($debt->notes ? " ({$debt->notes})" : ''),
                ]);
            }

            return $debt;
        });
    }

    /**
     * Record an installment payment or full settlement for a debt/receivable.
     * Supports underpayment with write-off settlement and overpayment with tip/rounding.
     */
    public function recordPayment(Debt $debt, array $data): DebtPayment
    {
        return DB::transaction(function () use ($debt, $data) {
            /** @var Debt $lockedDebt */
            $lockedDebt = Debt::where('id', $debt->id)->lockForUpdate()->firstOrFail();

            $paymentAmount = (float) $data['amount'];
            if ($paymentAmount <= 0) {
                throw new InvalidArgumentException('Nominal pembayaran harus lebih dari 0.');
            }

            $user = $lockedDebt->user;
            $accountId = $data['account_id'];
            $markAsSettled = ! empty($data['mark_as_settled']);
            $currentRemaining = (float) $lockedDebt->remaining_amount;

            $txType = $lockedDebt->type === 'debt' ? 'expense' : 'income';
            $txDescription = $lockedDebt->type === 'debt'
                ? "Bayar cicilan utang kepada {$lockedDebt->party_name}"
                : "Terima cicilan piutang dari {$lockedDebt->party_name}";

            // Add note context for underpayment (settlement with write-off) or overpayment (tip/rounding)
            $extraNote = '';
            if ($paymentAmount < $currentRemaining && $markAsSettled) {
                $diff = $currentRemaining - $paymentAmount;
                $extraNote = ' (Pelunasan disepakati, toleransi sisa Rp ' . number_format($diff, 0, ',', '.') . ' diikhlaskan)';
            } elseif ($paymentAmount > $currentRemaining) {
                $excess = $paymentAmount - $currentRemaining;
                $extraNote = ' (Pelunasan lunas dengan kelebihan Rp ' . number_format($excess, 0, ',', '.') . ' pembulatan/tip)';
            }

            $userNote = ! empty($data['notes']) ? " - {$data['notes']}" : '';
            $fullNotes = $txDescription . $extraNote . $userNote;

            // 1. Mutate account balance according to the actual money transferred
            $transaction = $this->transactionService->createTransaction($user, [
                'account_id' => $accountId,
                'type' => $txType,
                'amount' => $paymentAmount,
                'transaction_date' => $data['payment_date'] ?? now()->toDateString(),
                'notes' => $fullNotes,
            ]);

            // 2. Create debt payment record
            $payment = DebtPayment::create([
                'debt_id' => $lockedDebt->id,
                'transaction_id' => $transaction->id,
                'amount' => $paymentAmount,
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'notes' => (! empty($data['notes']) ? $data['notes'] : '') . $extraNote,
            ]);

            // 3. Update remaining debt amount and status
            if ($markAsSettled || $paymentAmount >= $currentRemaining) {
                $lockedDebt->remaining_amount = 0;
                $lockedDebt->status = 'paid';
            } else {
                $lockedDebt->remaining_amount = round($currentRemaining - $paymentAmount, 2);
                $lockedDebt->status = $lockedDebt->remaining_amount <= 0 ? 'paid' : 'partially_paid';
            }

            $lockedDebt->save();

            // Broadcast debt status changed safely
            try {
                event(new DebtStatusChanged(
                    userId: $user->id,
                    debtId: $lockedDebt->id,
                    remainingAmount: (float) $lockedDebt->remaining_amount,
                    status: $lockedDebt->status
                ));
            } catch (Throwable $e) {
                report($e);
            }

            return $payment->load(['debt', 'transaction']);
        });
    }

    /**
     * Write-off leftover small balance directly without transferring additional funds.
     * Useful when a tiny remaining amount (e.g. Rp 3.000) is agreed to be forgiven/settled later.
     */
    public function writeOff(Debt $debt, ?string $reason = null): Debt
    {
        return DB::transaction(function () use ($debt, $reason) {
            /** @var Debt $lockedDebt */
            $lockedDebt = Debt::where('id', $debt->id)->lockForUpdate()->firstOrFail();

            $forgivenAmount = (float) $lockedDebt->remaining_amount;
            $note = 'Penyesuaian pelunasan: Sisa tagihan Rp ' . number_format($forgivenAmount, 0, ',', '.') . ' diikhlaskan / dihapuskan.';
            if ($reason) {
                $note .= ' Alasan: ' . $reason;
            }

            $lockedDebt->remaining_amount = 0;
            $lockedDebt->status = 'paid';
            $lockedDebt->notes = $lockedDebt->notes ? ($lockedDebt->notes . ' | ' . $note) : $note;
            $lockedDebt->save();

            try {
                event(new DebtStatusChanged(
                    userId: $lockedDebt->user_id,
                    debtId: $lockedDebt->id,
                    remainingAmount: 0,
                    status: 'paid'
                ));
            } catch (Throwable $e) {
                report($e);
            }

            return $lockedDebt;
        });
    }

    /**
     * Delete a debt/receivable.
     */
    public function deleteDebt(Debt $debt): void
    {
        $debt->delete();
    }
}
