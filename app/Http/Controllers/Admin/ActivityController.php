<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QrCodeActivity;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The audit trail: who repointed which printed code, and when. */
class ActivityController extends Controller
{
    public function __invoke(Request $request): View
    {
        $activities = QrCodeActivity::query()
            ->with(['actor:id,name,role', 'qrCode:id,uuid,code,label,user_id', 'qrCode.owner:id,name'])
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->when($request->filled('actor_id'), fn ($q) => $q->where('actor_id', $request->integer('actor_id')))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.activity', [
            'activities' => $activities,
            'filters' => $request->only('type', 'actor_id'),
            'types' => [
                QrCodeActivity::TYPE_CREATED => 'Created',
                QrCodeActivity::TYPE_ASSIGNED => 'Assigned',
                QrCodeActivity::TYPE_UNASSIGNED => 'Unassigned',
                QrCodeActivity::TYPE_URL_CHANGED => 'Destination changed',
                QrCodeActivity::TYPE_ACTIVATED => 'Resumed',
                QrCodeActivity::TYPE_DEACTIVATED => 'Paused',
                QrCodeActivity::TYPE_UPDATED => 'Updated',
                QrCodeActivity::TYPE_DELETED => 'Deleted',
            ],
        ]);
    }
}
