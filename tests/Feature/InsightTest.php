<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use App\Services\FinancialInsightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InsightTest extends TestCase
{
    use RefreshDatabase;

    private function seedData(User $user): void
    {
        $account = Account::create([
            'user_id' => $user->id, 'name' => 'BCA', 'type' => 'bank',
            'initial_balance' => 5000000, 'current_balance' => 5000000,
        ]);
        $cat = Category::create(['user_id' => $user->id, 'name' => 'Konsumsi', 'type' => 'expense']);
        $inc = Category::create(['user_id' => $user->id, 'name' => 'Gaji', 'type' => 'income']);

        foreach ([[$inc, 'income', 5000000], [$cat, 'expense', 1000000]] as [$c, $type, $amt]) {
            $this->actingAs($user)->post(route('transactions.store'), [
                'account_id' => $account->id, 'category_id' => $c->id, 'type' => $type,
                'amount' => $amt, 'transaction_date' => now()->toDateString(),
            ]);
        }
    }

    public function test_insight_page_works_without_ai_key(): void
    {
        config(['services.gemini.key' => null]);
        $user = User::factory()->create();
        $this->seedData($user);

        $this->actingAs($user)->get(route('insights.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Insights/Index')
                ->where('insight.ai_enabled', false)
                ->where('insight.ai_narrative', null)
                ->where('insight.metrics.savings_rate', 80)
                ->where('insight.evaluation.label', 'Sangat Sehat'));
    }

    public function test_ai_narrative_used_when_gemini_responds(): void
    {
        config(['services.gemini.key' => 'fake']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Keuangan Anda baik.']]]]],
        ])]);
        $user = User::factory()->create();
        $this->seedData($user);

        $this->actingAs($user)->get(route('insights.index'))
            ->assertInertia(fn ($p) => $p->where('insight.ai_narrative', 'Keuangan Anda baik.'));
    }

    public function test_falls_back_when_gemini_fails(): void
    {
        config(['services.gemini.key' => 'fake']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 500)]);
        $user = User::factory()->create();
        $this->seedData($user);

        $this->actingAs($user)->get(route('insights.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('insight.ai_narrative', null));
    }

    public function test_empty_period_has_no_score(): void
    {
        $user = User::factory()->create();
        $period = app(\App\Services\PeriodService::class)->getActivePeriod($user);
        $result = app(FinancialInsightService::class)->analyze($user, $period);

        $this->assertNull($result['evaluation']['score']);
    }
}
