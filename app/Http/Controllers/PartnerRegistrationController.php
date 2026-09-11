<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Pendaftaran partner dari halaman publik.
 *
 * Yang dihasilkan di sini cuma sebuah baris menunggu — tidak ada database, tidak
 * ada akun, tidak ada subdomain. Semua itu lahir saat seorang admin menyetujui.
 * Formulir yang terbuka untuk siapa saja tidak boleh bisa menyuruh server
 * membuat database.
 */
class PartnerRegistrationController extends Controller
{
    public function create(): View
    {
        return view('pages.partner-register', [
            'fitur' => Tenant::FEATURES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:63', 'lowercase',
                // Calon subdomain: huruf kecil, angka, dan tanda hubung di
                // tengah. Aturannya aturan DNS, bukan selera kami.
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                'unique:tenants,slug',
            ],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:tenants,owner_email'],
            'owner_phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'slug.regex' => 'Alamat toko hanya boleh huruf kecil, angka, dan tanda hubung di tengah.',
            'slug.unique' => 'Alamat toko itu sudah dipakai partner lain.',
            'owner_email.unique' => 'Email itu sudah pernah mendaftar.',
            'password.confirmed' => 'Ulangan sandi tidak sama.',
        ]);

        Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => $data['name'],
            'slug' => $data['slug'],
            'owner_name' => $data['owner_name'],
            'owner_email' => $data['owner_email'],
            'owner_phone' => $data['owner_phone'] ?? null,
            // Di-hash sejak detik ini. Sandi mentah tidak pernah menginap di
            // tabel mana pun, termasuk saat masih menunggu persetujuan.
            'owner_password' => Hash::make($data['password']),
            'status' => Tenant::MENUNGGU,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('partner.register')
            ->with('partner_terkirim', $data['slug']);
    }
}
