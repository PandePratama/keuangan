<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_income_increases_account_balance(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => 1000000,
            'current_balance' => 1000000,
        ]);

        $category = Category::create([
            'user_id' => $user->id,
            'name' => 'Bonus',
            'type' => 'income',
        ]);

        $response = $this->actingAs($user)->post(route('transactions.store'), [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'income',
            'amount' => 500000,
            'transaction_date' => now()->toDateString(),
            'notes' => 'Bonus Project',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals(1500000, $account->fresh()->current_balance);
    }

    public function test_recording_expense_decreases_account_balance(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'GoPay',
            'type' => 'ewallet',
            'initial_balance' => 200000,
            'current_balance' => 200000,
        ]);

        $category = Category::create([
            'user_id' => $user->id,
            'name' => 'Makanan',
            'type' => 'expense',
        ]);

        $response = $this->actingAs($user)->post(route('transactions.store'), [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'amount' => 50000,
            'transaction_date' => now()->toDateString(),
            'notes' => 'Makan malam',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals(150000, $account->fresh()->current_balance);
    }

    public function test_transfer_moves_balance_between_accounts(): void
    {
        $user = User::factory()->create();
        $source = Account::create([
            'user_id' => $user->id,
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => 1000000,
            'current_balance' => 1000000,
        ]);
        $target = Account::create([
            'user_id' => $user->id,
            'name' => 'Dompet Cash',
            'type' => 'cash',
            'initial_balance' => 50000,
            'current_balance' => 50000,
        ]);

        $response = $this->actingAs($user)->post(route('transactions.store'), [
            'account_id' => $source->id,
            'to_account_id' => $target->id,
            'type' => 'transfer',
            'amount' => 200000,
            'transaction_date' => now()->toDateString(),
            'notes' => 'Tarik Tunai ATM',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals(800000, $source->fresh()->current_balance);
        $this->assertEquals(250000, $target->fresh()->current_balance);
    }

    public function test_deleting_transaction_reverts_account_balance(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => 1000000,
            'current_balance' => 1000000,
        ]);

        // Record expense
        $this->actingAs($user)->post(route('transactions.store'), [
            'account_id' => $account->id,
            'type' => 'expense',
            'amount' => 300000,
            'transaction_date' => now()->toDateString(),
        ]);

        $this->assertEquals(700000, $account->fresh()->current_balance);
        $transaction = Transaction::where('account_id', $account->id)->first();

        // Delete expense
        $this->actingAs($user)->delete(route('transactions.destroy', $transaction->id));

        $this->assertEquals(1000000, $account->fresh()->current_balance);
        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    }

    public function test_transactions_can_be_filtered_by_expense_category(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => 2000000,
            'current_balance' => 2000000,
        ]);

        $catKonsumsi = Category::create([
            'user_id' => $user->id,
            'name' => 'Konsumsi',
            'type' => 'expense',
        ]);

        $catBensin = Category::create([
            'user_id' => $user->id,
            'name' => 'Bensin',
            'type' => 'expense',
        ]);

        // Create transaction Konsumsi
        $this->actingAs($user)->post(route('transactions.store'), [
            'account_id' => $account->id,
            'category_id' => $catKonsumsi->id,
            'type' => 'expense',
            'amount' => 50000,
            'transaction_date' => now()->toDateString(),
            'notes' => 'Makan Siang',
        ]);

        // Create transaction Bensin
        $this->actingAs($user)->post(route('transactions.store'), [
            'account_id' => $account->id,
            'category_id' => $catBensin->id,
            'type' => 'expense',
            'amount' => 30000,
            'transaction_date' => now()->toDateString(),
            'notes' => 'Isi Bensin Motor',
        ]);

        // Request transactions with category filter Konsumsi
        $response = $this->actingAs($user)->get(route('transactions.index', [
            'category_id' => $catKonsumsi->id,
        ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Transactions/Index')
            ->has('transactions.data', 1)
            ->where('transactions.data.0.notes', 'Makan Siang')
            ->where('stats.expense', 50000)
        );
    }
}
