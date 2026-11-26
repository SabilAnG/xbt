<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use App\Support\AdminNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Pengguna panel & perannya. Hanya pemilik yang bisa membuka menu ini. */
class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->with('roleRecord')->orderByDesc('is_active')->orderBy('name')->get(),
            'roles' => Role::query()->withCount('users')->orderByDesc('is_system')->orderBy('id')->get(),
            'features' => AdminNavigation::featureChoices(),
        ]);
    }

    /** Riwayat siapa mencatat/mengubah/menghapus apa. */
    public function activity(Request $request): View
    {
        $logs = ActivityLog::query()->with('user')
            ->when($request->input('pengguna'), fn ($query, $user) => $query->where('user_id', $user))
            ->when($request->input('aksi'), fn ($query, $action) => $query->where('action', $action))
            ->when($request->input('dari'), fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($request->input('sampai'), fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->when($request->string('q')->trim()->value(), fn ($query, $search) => $query->where('description', 'like', "%{$search}%"))
            ->latest('id')->paginate(50)->withQueryString();

        return view('admin.users.activity', [
            'logs' => $logs,
            'users' => User::query()->orderBy('name')->pluck('name', 'id'),
            'actions' => ActivityLog::ACTIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::exists('roles', 'key')],
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
            'role' => ['required', Rule::exists('roles', 'key')],
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

    /** Peran baru dengan fitur yang dicentang. */
    public function storeRole(Request $request): RedirectResponse
    {
        $data = $this->validatedRole($request);
        $key = Str::slug($data['name']) ?: 'peran';
        for ($i = 2, $base = $key; Role::where('key', $key)->exists(); $i++) {
            $key = $base.'-'.$i;
        }

        Role::create($data + ['key' => $key]);

        return back()->with('status', 'Peran '.$data['name'].' ditambahkan.');
    }

    public function updateRole(Request $request, Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->with('error', 'Peran Pemilik selalu bisa membuka semua menu dan tidak bisa diubah.');
        }

        $role->update($this->validatedRole($request, $role));

        return back()->with('status', 'Hak akses '.$role->name.' disimpan.');
    }

    public function destroyRole(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->with('error', 'Peran Pemilik tidak bisa dihapus.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', 'Masih ada pengguna dengan peran '.$role->name.'. Pindahkan ke peran lain dulu.');
        }

        $role->delete();

        return back()->with('status', 'Peran '.$role->name.' dihapus.');
    }

    /** @return array{name: string, description: ?string, all_access: bool, permissions: list<string>} */
    private function validatedRole(Request $request, ?Role $role = null): array
    {
        $features = collect(AdminNavigation::featureChoices())->flatMap(fn (array $items) => array_keys($items))->all();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in($features)],
        ], ['name.unique' => 'Nama peran itu sudah ada.']);

        $all = $request->boolean('all_access');
        $permissions = $all ? [] : array_values(array_unique($data['permissions'] ?? []));

        if (! $all && $permissions === []) {
            throw ValidationException::withMessages(['permissions' => 'Centang minimal satu fitur, atau pilih "Semua menu".']);
        }

        return ['name' => $data['name'], 'description' => $data['description'] ?? null, 'all_access' => $all, 'permissions' => $permissions];
    }

    private function ownerCount(): int
    {
        return User::query()->where('role', User::OWNER)->where('is_active', true)->count();
    }
}
