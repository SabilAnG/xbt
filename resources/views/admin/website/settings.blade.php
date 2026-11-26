@extends('layouts.admin', ['title' => 'Pengaturan Situs'])

@section('actions')
    <a href="{{ route('home') }}" target="_blank" class="btn">Lihat website ↗</a>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.site-settings.update') }}" enctype="multipart/form-data" class="max-w-4xl space-y-6">
        @csrf
        @method('PUT')

        @foreach ($schema as $group => $section)
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">{{ $section['title'] }}</h2>
                    @isset($section['hint'])<p class="text-xs text-slate-500">{{ $section['hint'] }}</p>@endisset
                </div>
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    @foreach ($section['fields'] as $key => $field)
                        @php
                            [$label, $type] = $field;
                            $name = \App\Http\Controllers\Admin\SiteSettingController::inputName($key);
                            $value = $values[$key] ?? null;
                        @endphp
                        @if ($type === 'image')
                            <div>
                                <label class="label" for="{{ $name }}">{{ $label }}</label>
                                <div class="flex items-center gap-3">
                                    <div class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-lg border border-slate-200 bg-slate-800">
                                        @if ($value)
                                            <img src="{{ asset($value) }}" alt="" class="max-h-full max-w-full object-contain">
                                        @else
                                            <span class="text-[10px] text-slate-400">kosong</span>
                                        @endif
                                    </div>
                                    <input id="{{ $name }}" name="{{ $name }}" type="file" accept="image/*,.ico" class="input file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs">
                                </div>
                                @isset($field[2])<p class="hint">{{ $field[2] }}</p>@endisset
                                @error($name)<p class="error">{{ $message }}</p>@enderror
                            </div>
                        @else
                            <x-admin.field :name="$name" :label="$label" :value="$value"
                                :type="match ($type) { 'email' => 'email', 'url' => 'url', 'phone' => 'tel', default => 'text' }"
                                :required="$key === 'site.name'" :hint="$field[2] ?? null"
                                :class="$key === 'contact.address' ? 'sm:col-span-2' : ''" />
                        @endif
                    @endforeach
                </div>
            </section>
        @endforeach

        <div class="flex justify-end">
            <button class="btn btn-primary">Simpan pengaturan</button>
        </div>
    </form>
@endsection
