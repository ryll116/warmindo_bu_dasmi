<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', ['users' => User::orderBy('name')->orderBy('id')->paginate(15)]);
    }

    public function create(): View
    {
        return view('admin.users.create', ['user' => new User(['role' => 'kasir'])]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        User::create($request->validated());

        return to_route('admin.users.index')->with('success', 'User berhasil ditambahkan.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $admins = User::where('role', 'admin')->orderBy('id')->lockForUpdate()->get();
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $data = $request->validated();
            if ($locked->isAdmin() && $data['role'] !== 'admin' && $admins->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'Admin terakhir tidak dapat diubah menjadi Kasir.']);
            }
            if ($locked->is($request->user()) && $data['role'] !== $locked->role) {
                throw ValidationException::withMessages(['role' => 'Role akun yang sedang digunakan tidak dapat diubah. Gunakan akun Admin lain.']);
            }
            if (empty($data['password'])) {
                unset($data['password']);
            }
            $locked->update($data);
        }, 3);

        return to_route('admin.users.index')->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $admins = User::where('role', 'admin')->orderBy('id')->lockForUpdate()->get();
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($locked->isAdmin() && $admins->count() <= 1) {
                throw ValidationException::withMessages(['user' => 'Admin terakhir tidak dapat dihapus.']);
            }
            if ($locked->is($request->user())) {
                throw ValidationException::withMessages(['user' => 'Akun yang sedang digunakan tidak dapat dihapus.']);
            }
            $locked->delete();
        }, 3);

        return to_route('admin.users.index')->with('success', 'User berhasil dihapus.');
    }
}
