<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbuseReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AbuseReportController extends Controller
{
    public function index(Request $request): View
    {
        $reports = AbuseReport::with('video.user')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(30);

        return view('admin.abuse-reports.index', ['reports' => $reports]);
    }

    public function update(Request $request, AbuseReport $abuseReport): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:resolved,dismissed'],
        ]);

        $abuseReport->update([
            'status' => $data['status'],
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        return back()->with('status', 'Đã cập nhật trạng thái báo cáo.');
    }
}
