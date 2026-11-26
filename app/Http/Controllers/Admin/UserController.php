<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Pengguna panel & perannya. Hanya pemilik yang bisa membuka menu ini. */
class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->orderByDesc('is_active')->orderBy('name')->get(),
            'roles' => User::ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => ['required', Password::min(8)],
        ]);

        User::create($data + ['is_active' => true]);

        return back()->with('status', 'Pengguna '.$data['name'].' ditambahkan.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => ['nullable', Password::min(8)],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        if ($user->isOwner() && ($data['role'] !== User::OWNER || ! $data['is_active']) && $this->ownerCount() <= 1) {
            return back()->with('error', 'Harus ada minimal satu pemilik aktif.');
        }

        if (blank($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return back()->with('status', 'Pengguna '.$user->name.' disimpan.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Tidak bisa menghapus akun sendiri.');
        }

        if ($user->isOwner() && $this->ownerCount() <= 1) {
            return back()->with('error', 'Harus ada minimal satu pemilik aktif.');
        }

        $user->delete();

        return back()->with('status', 'Pengguna dihapus.');
    }

    private function ownerCount(): int
    {
        return User::query()->where('role', User::OWNER)->where('is_active', true)->count();
    }
}
