<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminAccountStoreRequest;
use App\Http\Requests\Admin\AdminAccountUpdateRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $admin = $request->user();
        abort_unless($admin?->isAdmin(), 403);

        $search = trim((string) $request->input('search', ''));
        $scope = (string) $request->input('scope', 'all');
        $allowedScopes = ['all', 'mine', 'others'];
        $scope = in_array($scope, $allowedScopes, true) ? $scope : 'all';

        $perPage = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50], true) ? $perPage : 10;

        $admins = User::query()
            ->whereIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $nested) use ($search) {
                    $nested
                        ->where('name', 'ilike', '%'.$search.'%')
                        ->orWhere('email', 'ilike', '%'.$search.'%');
                });
            })
            ->when($scope === 'mine', fn (Builder $query) => $query->whereKey($admin->id))
            ->when($scope === 'others', fn (Builder $query) => $query->whereKeyNot($admin->id))
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        $start = $admins->firstItem() ?? 1;
        $admins->setCollection(
            $admins->getCollection()->values()->map(fn (User $listedAdmin, int $index) => [
                'id' => $listedAdmin->id,
                'number' => $start + $index,
                'name' => $listedAdmin->name,
                'email' => $listedAdmin->email,
                'role' => $listedAdmin->role,
                'created_at' => optional($listedAdmin->created_at)->format('Y-m-d'),
                'is_self' => $listedAdmin->is($admin),
                'can_edit' => $admin->isSuperAdmin(),
                'can_delete' => $admin->isSuperAdmin() && ! $listedAdmin->is($admin) && ! $listedAdmin->isSuperAdmin(),
            ]),
        );

        return Inertia::render('Admin/Admins/Index', [
            'admins' => $admins,
            'filters' => [
                'search' => $search,
                'scope' => $scope,
                'per_page' => $perPage,
            ],
            'capabilities' => [
                'is_super_admin' => $admin->isSuperAdmin(),
            ],
            'status' => session('status'),
        ]);
    }

    public function create(): Response
    {
        abort_unless(request()->user()?->isSuperAdmin(), 403);

        return Inertia::render('Admin/Admins/Create');
    }

    public function store(AdminAccountStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        User::query()->create([
            'name' => trim($validated['name']),
            'email' => $validated['email'],
            'role' => User::ROLE_ADMIN,
            'password' => $validated['password'],
            'email_verified_at' => now(),
        ]);

        return redirect()
            ->route('admin.admins.index')
            ->with('status', 'admin-account-created');
    }

    public function edit(User $admin): Response
    {
        $currentAdmin = request()->user();
        abort_unless($currentAdmin?->isSuperAdmin(), 403);
        abort_unless($admin->isAdmin(), 404);

        return Inertia::render('Admin/Admins/Edit', [
            'adminAccount' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role,
                'created_at' => optional($admin->created_at)->format('Y-m-d'),
                'is_self' => $admin->is($currentAdmin),
                'can_change_role' => ! $admin->is($currentAdmin),
            ],
        ]);
    }

    public function update(AdminAccountUpdateRequest $request, User $admin): RedirectResponse
    {
        $currentAdmin = $request->user();
        abort_unless($currentAdmin?->isSuperAdmin(), 403);
        abort_unless($admin->isAdmin(), 404);

        $validated = $request->validated();

        if ($admin->is($currentAdmin)) {
            $validated['role'] = $admin->role;
        }

        if (($validated['role'] ?? $admin->role) === User::ROLE_SUPER_ADMIN && ! $admin->isSuperAdmin()) {
            $validated['role'] = User::ROLE_ADMIN;
        }

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        $admin->fill($validated);

        if ($admin->isDirty('email')) {
            $admin->email_verified_at = null;
        }

        $admin->save();

        return redirect()
            ->route('admin.admins.edit', $admin)
            ->with('status', 'admin-account-updated');
    }

    public function destroy(Request $request, User $admin): RedirectResponse
    {
        $currentAdmin = $request->user();
        abort_unless($currentAdmin?->isSuperAdmin(), 403);
        abort_unless($admin->isAdmin(), 404);
        abort_if($admin->is($currentAdmin), 403);
        abort_if($admin->isSuperAdmin(), 403);

        DB::transaction(function () use ($admin) {
            DB::table('sessions')->where('user_id', $admin->id)->delete();
            $admin->delete();
        });

        return redirect()
            ->route('admin.admins.index')
            ->with('status', 'admin-account-deleted');
    }
}
