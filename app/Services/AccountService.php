<?php

namespace App\Services;

use App\Events\BalanceUpdated;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class AccountService
{
    /**
     * Create a new financial account.
     */
    public function createAccount(User $user, array $data): Account
    {
        return DB::transaction(function () use ($user, $data) {
            $initialBalance = (float) ($data['initial_balance'] ?? 0);

            $account = $user->accounts()->create([
                'name' => $data['name'],
                'type' => $data['type'] ?? 'bank',
                'account_number' => $data['account_number'] ?? null,
                'initial_balance' => $initialBalance,
                'current_balance' => $initialBalance,
                'color_hex' => $data['color_hex'] ?? '#10B981',
                'icon' => $data['icon'] ?? 'wallet',
                'is_active' => true,
            ]);

            return $account;
        });
    }

    /**
     * Update account details.
     */
    public function updateAccount(Account $account, array $data): Account
    {
        $account->update([
            'name' => $data['name'] ?? $account->name,
            'type' => $data['type'] ?? $account->type,
            'account_number' => $data['account_number'] ?? $account->account_number,
            'color_hex' => $data['color_hex'] ?? $account->color_hex,
            'icon' => $data['icon'] ?? $account->icon,
            'is_active' => $data['is_active'] ?? $account->is_active,
        ]);

        return $account;
    }

    /**
     * Perform manual balance adjustment (FR-ACC-04).
     * Calculates the discrepancy between actual and recorded balance,
     * logs an adjustment transaction, and updates the account balance atomically.
     */
    public function adjustBalance(Account $account, float $actualBalance, ?string $notes = null): Account
    {
        return DB::transaction(function () use ($account, $actualBalance, $notes) {
            /** @var Account $lockedAccount */
            $lockedAccount = Account::where('id', $account->id)->lockForUpdate()->firstOrFail();

            $difference = round($actualBalance - (float) $lockedAccount->current_balance, 2);

            if ($difference !== 0.0) {
                $type = $difference > 0 ? 'income' : 'expense';
                $category = Category::where('user_id', $lockedAccount->user_id)
                    ->where('name', 'Penyesuaian Saldo')
                    ->first();

                if (! $category) {
                    $category = Category::create([
                        'user_id' => $lockedAccount->user_id,
                        'name' => 'Penyesuaian Saldo',
                        'type' => $type,
                        'icon' => 'sliders',
                        'color_hex' => '#6B7280',
                    ]);
                }

                Transaction::create([
                    'user_id' => $lockedAccount->user_id,
                    'account_id' => $lockedAccount->id,
                    'category_id' => $category->id,
                    'type' => $type,
                    'amount' => abs($difference),
                    'transaction_date' => now()->toDateString(),
                    'notes' => $notes ?: ('Koreksi saldo manual (selisih ' . ($difference > 0 ? '+' : '-') . 'Rp ' . number_format(abs($difference), 0, ',', '.') . ')'),
                ]);

                $lockedAccount->current_balance = $actualBalance;
                $lockedAccount->save();

                try {
                    event(new BalanceUpdated(
                        userId: $lockedAccount->user_id,
                        accountId: $lockedAccount->id,
                        currentBalance: (float) $lockedAccount->current_balance,
                        accountName: $lockedAccount->name
                    ));
                } catch (Throwable $e) {
                    report($e);
                }
            }

            return $lockedAccount;
        });
    }

    /**
     * Bulk reconcile multiple accounts at once (e.g. at end of month).
     */
    public function reconcileBulk(User $user, array $adjustments, ?string $globalNotes = null): array
    {
        $updated = [];
        foreach ($adjustments as $adj) {
            if (! isset($adj['account_id'], $adj['actual_balance'])) {
                continue;
            }

            $account = $user->accounts()->where('id', $adj['account_id'])->first();
            if ($account) {
                $note = $adj['notes'] ?? $globalNotes ?? 'Rekonsiliasi & koreksi saldo akhir bulan';
                $updated[] = $this->adjustBalance($account, (float) $adj['actual_balance'], $note);
            }
        }

        return $updated;
    }

    /**
     * Toggle active status of an account.
     */
    public function toggleActive(Account $account): Account
    {
        $account->is_active = ! $account->is_active;
        $account->save();

        return $account;
    }

    /**
     * Delete an account.
     */
    public function deleteAccount(Account $account): void
    {
        $account->delete();
    }
}
