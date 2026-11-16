<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Models\UserLog;
use App\Services\Billing\QuotaService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request, QuotaService $quota): View
    {
        $users = User::with('role')
            ->when($request->query('q'), fn ($query, $q) => $query->where(
                fn ($w) => $w->where('email', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%")
            ))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::all(),
            'plans' => Plan::where('is_active', true)->where('slug', '!=', Plan::FREE_SLUG)->orderBy('sort')->get(),
            'quota' => $quota,
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['role_id' => ['required', 'exists:roles,id']]);

        abort_if($user->is($request->user()) && (int) $data['role_id'] !== Role::ADMIN, 422, 'Không thể tự hạ quyền admin của chính mình.');

        $user->forceFill(['role_id' => $data['role_id']])->save();

        return back()->with('status', 'Đã đổi role.');
    }

    public function grantPlan(Request $request, User $user, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $subscriptions->grant($user, Plan::findOrFail($data['plan_id']), $data['days'] ?? null);

        return back()->with('status', 'Đã cấp/gia hạn gói.');
    }

    public function ban(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'Không thể tự ban chính mình.');
        abort_if($user->isAdmin(), 422, 'Không thể ban admin.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $user->update([
            'banned_at' => now(),
            'banned_reason' => $data['reason'],
            'banned_by' => $request->user()->id,
        ]);

        UserLog::record($user->id, UserLog::USER_BANNED, ['reason' => $data['reason'], 'by' => $request->user()->email], $request->ip());

        return back()->with('status', 'Đã khóa tài khoản.');
    }

    public function unban(Request $request, User $user): RedirectResponse
    {
        $user->update([
            'banned_at' => null,
            'banned_reason' => null,
            'banned_by' => null,
        ]);

        UserLog::record($user->id, UserLog::USER_UNBANNED, ['by' => $request->user()->email], $request->ip());

        return back()->with('status', 'Đã mở khóa tài khoản.');
    }

    public function show(User $user, QuotaService $quota): View
    {
        $user->load(['role', 'banner', 'subscriptions.plan']);

        $logs = UserLog::where('user_id', $user->id)
            ->latest()
            ->paginate(20);

        return view('admin.users.show', [
            'user' => $user,
            'quota' => $quota,
            'logs' => $logs,
            'roles' => Role::all(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return back()->with('status', 'Đã cập nhật thông tin.');
    }
}
