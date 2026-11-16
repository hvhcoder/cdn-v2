<?php

namespace App\Http\Controllers;

use App\Services\Billing\QuotaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function show(Request $request, QuotaService $quota): View
    {
        $user = $request->user();

        return view('subscription', [
            'plan' => $quota->planFor($user),
            'usage' => $quota->usage($user),
            'subscription' => $user->subscriptions()->current()->latest('ends_at')->first(),
        ]);
    }
}
