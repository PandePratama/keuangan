<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_accounts_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('accounts.index'));

        $response->assertStatus(200);
    }

    public function test_user_can_create_an_account(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('accounts.store'), [
            'name' => 'BCA Syariah',
            'type' => 'bank',
            'account_number' => '123456789',
            'initial_balance' => 500000,
            'color_hex' => '#10B981',
            'icon' => 'landmark',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'name' => 'BCA Syariah',
            'current_balance' => 500000,
        ]);
    }

    public function test_user_can_adjust_account_balance_manually(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Dompet Cash',
            'type' => 'cash',
            'initial_balance' => 100000,
            'current_balance' => 100000,
            'color_hex' => '#10B981',
            'icon' => 'wallet',
        ]);

        $response = $this->actingAs($user)->post(route('accounts.adjust', $account->id), [
            'actual_balance' => 150000,
            'notes' => 'Menemukan uang di saku jaket',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals(150000, $account->fresh()->current_balance);
        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'type' => 'income',
            'amount' => 50000,
        ]);
    }

    public function test_user_cannot_adjust_another_users_account(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $account = Account::create([
            'user_id' => $user2->id,
            'name' => 'Dompet User 2',
            'type' => 'cash',
            'initial_balance' => 100000,
            'current_balance' => 100000,
            'color_hex' => '#10B981',
            'icon' => 'wallet',
        ]);

        $response = $this->actingAs($user1)->post(route('accounts.adjust', $account->id), [
            'actual_balance' => 200000,
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_reconcile_bulk_accounts(): void
    {
        $user = User::factory()->create();
        $acc1 = Account::create([
            'user_id' => $user->id,
            'name' => 'Bank BCA',
            'type' => 'bank',
            'initial_balance' => 1000000,
            'current_balance' => 1000000,
        ]);
        $acc2 = Account::create([
            'user_id' => $user->id,
            'name' => 'Dompet Tunai',
            'type' => 'cash',
            'initial_balance' => 200000,
            'current_balance' => 200000,
        ]);

        $response = $this->actingAs($user)->post(route('accounts.reconcile-bulk'), [
            'adjustments' => [
                ['account_id' => $acc1->id, 'actual_balance' => 990000], // -10.000 biaya admin
                ['account_id' => $acc2->id, 'actual_balance' => 180000], // -20.000 uang parkir terlupa
            ],
            'notes' => 'Rekonsiliasi akhir bulan',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals(990000, $acc1->fresh()->current_balance);
        $this->assertEquals(180000, $acc2->fresh()->current_balance);
    }
}
