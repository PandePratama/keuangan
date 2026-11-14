<?php

namespace App\Http\Controllers;

use App\Services\FinancialInsightService;
use App\Services\PeriodService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InsightController extends Controller
{
    public function __construct(
        protected FinancialInsightService $insightService,
        protected PeriodService $periodService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $period = $this->periodService->getActivePeriod($user, $request->session()->get('active_period_id'));

        return Inertia::render('Insights/Index', [
            'period' => $period,
            'insight' => $this->insightService->analyze($user, $period),
            'currencySymbol' => $user->currency_symbol ?? 'Rp',
        ]);
    }
}
