<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\DashboardService;
use App\Services\PeriodService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected PeriodService $periodService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $activePeriodId = $request->session()->get('active_period_id');
        $activePeriod = $this->periodService->getActivePeriod($user, $activePeriodId);

        $summary = $this->dashboardService->getSummary($user, $activePeriod);
        $monthlyCashFlow = $this->dashboardService->getMonthlyCashFlow($user);
        $expenseByCategory = $this->dashboardService->getExpenseByCategory($user, $activePeriod);
        $recentTransactions = $this->dashboardService->getRecentTransactions($user, $activePeriod, 7);
        $upcomingDebts = $this->dashboardService->getUpcomingDebts($user, 5);

        $accounts = $user->accounts()->active()->get();
        $categories = Category::availableForUser($user->id)->get();

        return Inertia::render('Dashboard', [
            'summary' => $summary,
            'activePeriod' => $activePeriod,
            'monthlyCashFlow' => $monthlyCashFlow,
            'expenseByCategory' => $expenseByCategory,
            'recentTransactions' => $recentTransactions,
            'upcomingDebts' => $upcomingDebts,
            'accounts' => $accounts,
            'categories' => $categories,
        ]);
    }
}
