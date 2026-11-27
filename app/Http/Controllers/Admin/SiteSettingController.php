<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\StoresSiteUploads;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Pengaturan situs: identitas, kontak, WhatsApp, media sosial. */
class SiteSettingController extends Controller
{
    use StoresSiteUploads;

    /**
     * Grup → [key => [label, type, hint]]. Type menentukan input & validasi.
     *
     * @var array<string, array{title: string, hint?: string, fields: array<string, array{0: string, 1: string, 2?: string}>}>
     */
    public const SCHEMA = [
        'identity' => ['title' => 'Identitas', 'fields' => [
            'site.name' => ['Nama website', 'text'],
            'site.tagline' => ['Tagline', 'text'],
            'site.logo' => ['Logo (latar gelap)', 'image', 'Dipakai di header situs. Tulisannya putih.'],
            'site.logo_light' => ['Logo (latar terang)', 'image', 'Versi tulisan gelap.'],
            'site.favicon' => ['Favicon', 'image'],
        ]],
        'contact' => ['title' => 'Kontak', 'fields' => [
            'contact.email' => ['Email', 'email'],
            'contact.phone' => ['Telepon', 'phone'],
            'contact.address' => ['Alamat', 'text'],
        ]],
        'whatsapp' => ['title' => 'WhatsApp', 'hint' => 'Format internasional tanpa "+", mis. 62895337161221.', 'fields' => [
            'whatsapp.primary' => ['Nomor utama', 'phone'],
            'whatsapp.sales1' => ['Sales 1 (Admin 1)', 'phone'],
            'whatsapp.sales2' => ['Sales 2 (Admin 2)', 'phone'],
            'whatsapp.sales3' => ['Sales 3', 'phone'],
        ]],
        'social' => ['title' => 'Media sosial', 'fields' => [
            'social.instagram' => ['Instagram', 'url'],
            'social.facebook' => ['Facebook', 'url'],
            'social.tiktok' => ['TikTok', 'url'],
            'social.youtube' => ['YouTube', 'url'],
        ]],
    ];

    public function edit(): View
    {
        return view('admin.website.settings', [
            'schema' => self::SCHEMA,
            'values' => Setting::query()->pluck('value', 'key'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];

        foreach ($this->fields() as $key => [, $type, $group]) {
            $rules[self::inputName($key)] = match ($type) {
                'image' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,gif,ico', 'max:4096'],
                'email' => ['nullable', 'email', 'max:255'],
                'url' => ['nullable', 'url', 'max:500'],
                'phone' => ['nullable', 'regex:/^[0-9]{6,20}$/'],
                default => [$key === 'site.name' ? 'required' : 'nullable', 'string', 'max:500'],
            };
        }

        $data = $request->validate($rules, ['regex' => 'Hanya angka, tanpa spasi atau tanda +.']);

        foreach ($this->fields() as $key => [, $type, $group]) {
            $name = self::inputName($key);

            if ($type === 'image') {
                if ($request->hasFile($name)) {
                    $this->deleteSiteUpload(Setting::get($key));
                    $this->save($key, $this->storeSiteUpload($request->file($name), 'site'), $type, $group);
                }

                continue;
            }

            $this->save($key, $data[$name] ?? null, $type, $group);
        }

        return back()->with('status', 'Pengaturan situs disimpan.');
    }

    /** "site.name" → "site_name" supaya titik tidak dibaca sebagai nested oleh validator. */
    public static function inputName(string $key): string
    {
        return str_replace('.', '_', $key);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    private function fields(): array
    {
        $fields = [];

        foreach (self::SCHEMA as $group => $section) {
            foreach ($section['fields'] as $key => $field) {
                $fields[$key] = [$field[0], $field[1], $group];
            }
        }

        return $fields;
    }

    private function save(string $key, ?string $value, string $type, string $group): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'type' => $type, 'group' => $group]);
    }
}
