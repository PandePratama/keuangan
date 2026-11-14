<?php

namespace App\Services;

use App\Events\BalanceUpdated;
use App\Events\TransactionRecorded;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class TransactionService
{
    public function __construct(
        protected PeriodService $periodService
    ) {}

    /**
     * Create a new transaction with atomic balance updates and race-condition prevention.
     */
    public function createTransaction(User $user, array $data): Transaction
    {
        return DB::transaction(function () use ($user, $data) {
            $type = $data['type'];
            $amount = (float) $data['amount'];
            $sourceAccountId = $data['account_id'];
            $targetAccountId = $data['to_account_id'] ?? null;
            $txDate = $data['transaction_date'] ?? now()->toDateString();

            if ($amount <= 0) {
                throw new InvalidArgumentException('Nominal transaksi harus lebih dari 0.');
            }

            // Check financial period and ensure period is not closed
            $period = $this->periodService->getOrCreatePeriodForDate($user, Carbon::parse($txDate));
            if ($period->isClosed()) {
                throw new InvalidArgumentException("Buku periode {$period->name} telah ditutup (closed). Silakan buka kunci periode terlebih dahulu untuk menambah transaksi.");
            }

            // Lock source account for atomic update
            /** @var Account $sourceAccount */
            $sourceAccount = Account::where('id', $sourceAccountId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $targetAccount = null;
            if ($type === 'transfer') {
                if (! $targetAccountId || $targetAccountId == $sourceAccountId) {
                    throw new InvalidArgumentException('Akun tujuan transfer tidak valid.');
                }

                /** @var Account $targetAccount */
                $targetAccount = Account::where('id', $targetAccountId)
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            // Adjust account balances according to transaction type
            if ($type === 'income') {
                $sourceAccount->current_balance = (float) $sourceAccount->current_balance + $amount;
                $sourceAccount->save();
            } elseif ($type === 'expense') {
                $sourceAccount->current_balance = (float) $sourceAccount->current_balance - $amount;
                $sourceAccount->save();
            } elseif ($type === 'transfer') {
                $sourceAccount->current_balance = (float) $sourceAccount->current_balance - $amount;
                $sourceAccount->save();

                $targetAccount->current_balance = (float) $targetAccount->current_balance + $amount;
                $targetAccount->save();
            }

            // Create transaction record linked to current period
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'financial_period_id' => $period->id,
                'account_id' => $sourceAccount->id,
                'to_account_id' => $targetAccount?->id,
                'category_id' => $data['category_id'] ?? null,
                'type' => $type,
                'amount' => $amount,
                'transaction_date' => $txDate,
                'notes' => $data['notes'] ?? null,
                'receipt_image_path' => $data['receipt_image_path'] ?? null,
            ]);

            // Update period metrics
            $this->periodService->recalculateMetrics($period);

            // Safely broadcast real-time events without disrupting transactions
            try {
                event(new BalanceUpdated(
                    userId: $user->id,
                    accountId: $sourceAccount->id,
                    currentBalance: (float) $sourceAccount->current_balance,
                    accountName: $sourceAccount->name
                ));

                if ($targetAccount) {
                    event(new BalanceUpdated(
                        userId: $user->id,
                        accountId: $targetAccount->id,
                        currentBalance: (float) $targetAccount->current_balance,
                        accountName: $targetAccount->name
                    ));
                }

                event(new TransactionRecorded(
                    userId: $user->id,
                    transaction: [
                        'id' => $transaction->id,
                        'type' => $transaction->type,
                        'amount' => (float) $transaction->amount,
                        'account_name' => $sourceAccount->name,
                        'to_account_name' => $targetAccount?->name,
                        'transaction_date' => $transaction->transaction_date->format('Y-m-d'),
                        'notes' => $transaction->notes,
                    ]
                ));
            } catch (Throwable $e) {
                report($e);
            }

            return $transaction->load(['account', 'toAccount', 'category', 'financialPeriod']);
        });
    }

    /**
     * Delete transaction and safely revert account balance changes.
     */
    public function deleteTransaction(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $user = $transaction->user;
            $amount = (float) $transaction->amount;
            $period = $transaction->financialPeriod;

            if ($period && $period->isClosed()) {
                throw new InvalidArgumentException("Transaksi tidak dapat dihapus karena buku periode {$period->name} telah ditutup.");
            }

            // Revert balances
            /** @var Account $sourceAccount */
            $sourceAccount = Account::where('id', $transaction->account_id)->lockForUpdate()->first();

            if ($sourceAccount) {
                if ($transaction->type === 'income') {
                    $sourceAccount->current_balance = (float) $sourceAccount->current_balance - $amount;
                } elseif ($transaction->type === 'expense') {
                    $sourceAccount->current_balance = (float) $sourceAccount->current_balance + $amount;
                } elseif ($transaction->type === 'transfer') {
                    $sourceAccount->current_balance = (float) $sourceAccount->current_balance + $amount;

                    if ($transaction->to_account_id) {
                        /** @var Account $targetAccount */
                        $targetAccount = Account::where('id', $transaction->to_account_id)->lockForUpdate()->first();
                        if ($targetAccount) {
                            $targetAccount->current_balance = (float) $targetAccount->current_balance - $amount;
                            $targetAccount->save();

                            try {
                                event(new BalanceUpdated(
                                    userId: $user->id,
                                    accountId: $targetAccount->id,
                                    currentBalance: (float) $targetAccount->current_balance,
                                    accountName: $targetAccount->name
                                ));
                            } catch (Throwable $e) {
                                report($e);
                            }
                        }
                    }
                }
                $sourceAccount->save();

                try {
                    event(new BalanceUpdated(
                        userId: $user->id,
                        accountId: $sourceAccount->id,
                        currentBalance: (float) $sourceAccount->current_balance,
                        accountName: $sourceAccount->name
                    ));
                } catch (Throwable $e) {
                    report($e);
                }
            }

            $transaction->delete();

            if ($period) {
                $this->periodService->recalculateMetrics($period);
            }
        });
    }
}
