<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminMasters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRUD generik untuk master data pendukung Formula (HPP): komponen biaya,
 * mesin, overhead, tingkatan harga, merek & type motor.
 */
class MasterController extends Controller
{
    public function index(string $master): View
    {
        $config = AdminMasters::get($master);

        return view('admin.masters.index', [
            'master' => $master,
            'config' => $config,
            'rows' => $config['model']::query()->orderBy($config['order'])->orderBy('id')->get(),
        ]);
    }

    public function create(string $master): View
    {
        $config = AdminMasters::get($master);
        $row = new $config['model'];

        foreach ($config['fields'] as $name => $field) {
            if (array_key_exists('default', $field)) {
                $row->{$name} = $field['default'];
            }
        }

        return view('admin.masters.form', ['master' => $master, 'config' => $config, 'row' => $row]);
    }

    public function store(Request $request, string $master): RedirectResponse
    {
        $config = AdminMasters::get($master);
        $config['model']::create($this->validated($request, $config));

        return redirect()->route('admin.masters.index', $master)->with('status', ucfirst($config['singular']).' ditambahkan.');
    }

    public function edit(string $master, int $id): View
    {
        $config = AdminMasters::get($master);

        return view('admin.masters.form', ['master' => $master, 'config' => $config, 'row' => $config['model']::findOrFail($id)]);
    }

    public function update(Request $request, string $master, int $id): RedirectResponse
    {
        $config = AdminMasters::get($master);
        $row = $config['model']::findOrFail($id);
        $row->update($this->validated($request, $config, $row));

        return redirect()->route('admin.masters.index', $master)->with('status', ucfirst($config['singular']).' disimpan.');
    }

    public function destroy(string $master, int $id): RedirectResponse
    {
        $config = AdminMasters::get($master);
        $row = $config['model']::findOrFail($id);

        foreach ($config['in_use'] ?? [] as $relation => $label) {
            if ($row->{$relation}()->exists()) {
                return back()->with('error', "Masih dipakai di {$label}, jadi tidak bisa dihapus. Nonaktifkan saja.");
            }
        }

        $row->delete();

        return back()->with('status', ucfirst($config['singular']).' dihapus.');
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function validated(Request $request, array $config, ?Model $row = null): array
    {
        $model = new $config['model'];
        $rules = [];

        foreach ($config['fields'] as $name => $field) {
            $rules[$name] = match ($field['type']) {
                'number' => [($field['required'] ?? false) ? 'required' : 'nullable', 'numeric', 'min:0'],
                'select' => [($field['required'] ?? false) ? 'required' : 'nullable', Rule::in(array_keys(value($field['options'])))],
                'checkbox' => ['boolean'],
                default => [($field['required'] ?? false) ? 'required' : 'nullable', 'string', 'max:255'],
            };

            if ($field['unique'] ?? false) {
                $rules[$name][] = Rule::unique($model->getTable(), $name)->ignore($row);
            }
        }

        $data = $request->validate($rules);

        foreach ($config['fields'] as $name => $field) {
            if ($field['type'] === 'checkbox') {
                $data[$name] = $request->boolean($name);
            } elseif ($field['type'] === 'number' && ($data[$name] ?? null) === null && array_key_exists('default', $field)) {
                $data[$name] = $field['default'];
            }
        }

        // Slug unik diturunkan dari nama — tidak perlu dipikirkan admin.
        if ($column = $config['slug_column'] ?? null) {
            if (! $row || ! $row->{$column}) {
                $slug = Str::slug($data['name']);
                $exists = $config['model']::query()->where($column, $slug)->exists();
                $data[$column] = $exists ? $slug.'-'.Str::lower(Str::random(4)) : $slug;
            }
        }

        return $data;
    }
}
