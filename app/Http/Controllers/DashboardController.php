<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Models\VideoSegment;
use App\Services\Billing\QuotaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, QuotaService $quota): View
    {
        $user = $request->user();
        $plan = $quota->planFor($user);

        $recent = $user->videos()
            ->withCount(['segments as uploaded_segments_count' => fn ($q) => $q->where('status', VideoSegment::UPLOADED)])
            ->latest()->limit(6)->get();

        return view('dashboard', [
            'plan' => $plan,
            'usage' => $quota->usage($user),
            'subscription' => $user->subscriptions()->current()->latest('ends_at')->first(),
            'recent' => $recent,
            'processing' => $user->videos()->where('status', Video::PROCESSING)->count(),
            'completed' => $user->videos()->where('status', Video::COMPLETED)->count(),
            'canUpload' => Gate::allows('upload', Video::class),
        ]);
    }
}
