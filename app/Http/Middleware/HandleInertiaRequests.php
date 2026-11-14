<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Services\PeriodService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(
        protected PeriodService $periodService
    ) {}

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'accounts' => fn () => $user ? $user->accounts()->active()->get() : [],
            'categories' => fn () => $user ? Category::availableForUser($user->id)->get() : [],
            'periods' => fn () => $user ? $user->periods()->get() : [],
            'activePeriod' => fn () => $user ? $this->periodService->getActivePeriod($user, $request->session()->get('active_period_id')) : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
