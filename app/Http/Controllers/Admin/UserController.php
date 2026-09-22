<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\QrCode;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\QrCodeService;
use App\Support\SpreadsheetExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly QrCodeService $qrCodes,
        private readonly AnalyticsService $analytics,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->search($request->string('q')->toString())
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')->toString()))
            ->when($request->string('status')->toString() === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->string('status')->toString() === 'suspended', fn ($q) => $q->where('is_active', false))
            ->withCount([
                'qrCodes',
                'qrCodes as live_qr_codes_count' => fn ($q) => $q->where('is_active', true)->whereNotNull('target_url'),
            ])
            ->withSum('qrCodes as total_scans', 'scan_count')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $request->only('q', 'role', 'status'),
            'roles' => UserRole::options(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.create', [
            'roles' => $this->assignableRoles(),
            'generatedPassword' => Str::password(12, symbols: false),
        ]);
    }

    /**
     * Create the customer, and optionally mint their codes at the same time —
     * the "somebody just bought 10 standees" path.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        // Absent when the admin left the field blank: generate one and show it once.
        $password = $data['password'] ?? null ?: Str::password(12, symbols: false);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($password),
            'role' => $data['role'],
            'company' => $data['company'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $quantity = (int) ($data['qr_quantity'] ?? 0);

        if ($quantity > 0) {
            $this->qrCodes->generateBatch([
                'name' => $user->name.' — initial order',
                'quantity' => $quantity,
                'label_prefix' => $data['qr_label_prefix'] ?? null,
                'user_id' => $user->id,
            ], $request->user());
        }

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', $quantity > 0
                ? "Customer created with {$quantity} QR codes ready to configure."
                : 'Customer created.')
            // Shown once so the admin can pass the login on; never stored in plain text.
            ->with('generated_password', filled($data['password'] ?? null) ? null : $password);
    }

    public function show(Request $request, User $user): View
    {
        $this->authorize('view', $user);

        $days = 30;
        $scans = $this->analytics->forUser($user);

        return view('admin.users.show', [
            'user' => $user->loadCount('qrCodes'),
            'days' => $days,
            'summary' => $this->analytics->summary(clone $scans, $days),
            'series' => $this->analytics->timeSeries(clone $scans, $days),
            'devices' => $this->analytics->breakdown(clone $scans, 'device_type', 5, $days),
            'countries' => $this->analytics->breakdown(clone $scans, 'country_code', 5, $days),
            'qrCodes' => QrCode::ownedBy($user)
                ->orderByDesc('scan_count')
                ->paginate(10, ['*'], 'codes'),
            'unassignedCount' => QrCode::unassigned()->count(),
        ]);
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => $this->assignableRoles(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'company' => $data['company'] ?? null,
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'is_active' => $data['is_active'] ?? $user->is_active,
            'notes' => $data['notes'] ?? null,
        ]);

        if (filled($data['password'] ?? null)) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return redirect()->route('admin.users.show', $user)->with('status', 'Customer updated.');
    }

    /** Suspend or restore an account. Their QR codes keep redirecting either way. */
    public function toggle(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot suspend your own account.');
        }

        $user->forceFill(['is_active' => ! $user->is_active])->save();

        return back()->with('status', $user->is_active
            ? $user->name.' can sign in again.'
            : $user->name.' has been suspended. Their QR codes keep working.');
    }

    /** Issue a new password and show it once so the admin can pass it on. */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $password = Str::password(12, symbols: false);
        $user->forceFill(['password' => Hash::make($password)])->save();

        return back()
            ->with('status', 'New password generated for '.$user->name.'.')
            ->with('generated_password', $password);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        // Their codes are far more valuable than the account: return them to the
        // pool so they can be reassigned rather than deleted with the customer.
        $this->qrCodes->unassign(QrCode::ownedBy($user)->get(), $request->user());

        $name = $user->name;
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('status', $name.' was deleted. Their QR codes went back to the unassigned pool.');
    }

    public function export(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $rows = User::query()
            ->withCount('qrCodes')
            ->withSum('qrCodes as total_scans', 'scan_count')
            ->cursor()
            ->map(fn (User $user) => [
                $user->name,
                $user->email,
                $user->company,
                $user->phone,
                $user->role->label(),
                $user->is_active ? 'Active' : 'Suspended',
                $user->qr_codes_count,
                (int) $user->total_scans,
                $user->created_at?->format('Y-m-d'),
                $user->last_login_at?->format('Y-m-d H:i'),
            ]);

        return SpreadsheetExporter::download(
            $request->string('format')->toString(),
            'customers-'.now()->format('Y-m-d'),
            ['Name', 'Email', 'Company', 'Phone', 'Role', 'Status', 'QR codes', 'Total scans', 'Created', 'Last login'],
            $rows,
        );
    }

    /** @return array<string, string> */
    private function assignableRoles(): array
    {
        if (request()->user()->isSuperAdmin()) {
            return UserRole::options();
        }

        return [UserRole::User->value => UserRole::User->label()];
    }
}
