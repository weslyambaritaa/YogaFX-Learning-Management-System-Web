<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminAccountStoreRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            ->where('role', User::ROLE_ADMIN)
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
                'created_at' => optional($listedAdmin->created_at)->format('Y-m-d'),
                'is_self' => $listedAdmin->is($admin),
            ]),
        );

        return Inertia::render('Admin/Admins/Index', [
            'admins' => $admins,
            'filters' => [
                'search' => $search,
                'scope' => $scope,
                'per_page' => $perPage,
            ],
            'status' => session('status'),
        ]);
    }

    public function create(): Response
    {
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

    public function destroy(Request $request, User $admin): RedirectResponse
    {
        $currentAdmin = $request->user();
        abort_unless($currentAdmin?->isAdmin(), 403);
        abort_unless($admin->isAdmin(), 404);
        abort_unless($currentAdmin->is($admin), 403);

        DB::transaction(function () use ($admin) {
            DB::table('sessions')->where('user_id', $admin->id)->delete();
            $admin->delete();
        });

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'admin-account-deleted');
    }
}
