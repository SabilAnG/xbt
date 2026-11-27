@extends('layouts.admin', ['title' => $design->exists ? $design->name : 'Desain baru', 'subtitle' => 'Desain 3D knalpot dari ukuran leheran & silincer, dengan estimasi tambahan tenaga.'])

@php
    $p = $params;
    $sliders = [
        'header' => [
            ['header.diameter', 'Diameter dalam leheran', 18, 45, 0.5, 'mm'],
            ['header.length', 'Panjang leheran', 300, 1000, 5, 'mm'],
            ['header.wall', 'Tebal pipa', 0.8, 2, 0.1, 'mm'],
        ],
        'silencer' => [
            ['silencer.length', 'Panjang silincer', 150, 600, 5, 'mm'],
            ['silencer.width', 'Lebar / diameter silincer', 50, 180, 1, 'mm'],
            ['silencer.height', 'Tinggi silincer', 40, 180, 1, 'mm'],
            ['silencer.core', 'Diameter core (pipa dalam)', 18, 60, 0.5, 'mm'],
            ['silencer.inlet_cone', 'Corong masuk', 0, 150, 5, 'mm'],
            ['silencer.outlet_cone', 'Corong keluar', 0, 150, 5, 'mm'],
            ['silencer.tip_diameter', 'Diameter ujung (tip)', 20, 80, 1, 'mm'],
            ['silencer.angle', 'Sudut naik silincer', -5, 30, 1, '°'],
        ],
    ];
    $value = fn (string $key) => data_get($p, $key);
    $references = $design->referenceUrls();
@endphp

@section('actions')
    <a href="{{ route('admin.design-sims.index') }}" class="btn">Semua desain</a>
    @if ($design->exists)
        <form method="POST" action="{{ route('admin.design-sims.duplicate', $design) }}">@csrf<button class="btn">Salin desain</button></form>
        <form method="POST" action="{{ route('admin.design-sims.destroy', $design) }}" data-confirm="Hapus desain {{ $design->name }} beserta foto referensinya?" data-confirm-tone="danger">
            @csrf @method('DELETE')<button class="btn btn-danger">Hapus</button>
        </form>
    @endif
    <button type="submit" form="design-form" class="btn btn-primary">Simpan desain</button>
@endsection

@section('content')
<form id="design-form" method="POST" enctype="multipart/form-data"
      action="{{ $design->exists ? route('admin.design-sims.update', $design) : route('admin.design-sims.store') }}"
      class="ds-shell" data-design-sim
      data-simulate-url="{{ route('admin.design-sims.simulate') }}" data-auto-url="{{ route('admin.design-sims.auto') }}">
    @csrf
    @if ($design->exists) @method('PUT') @endif
    <input type="hidden" name="params" value="{{ json_encode($p) }}" data-params-input>
    <input type="hidden" name="thumbnail" data-thumbnail-input>
    <script type="application/json" data-initial-result>@json($result)</script>
    <script type="application/json" data-motor-photos>@json($motorPhotos)</script>

    {{-- ---------------------------------------------------- viewport 3D --}}
    <section class="ds-stage card">
        <div class="ds-toolbar">
            <div class="ds-segment" role="tablist" aria-label="Mode tampilan">
                <button type="button" class="is-active" data-mode="real">Realistis</button>
                <button type="button" data-mode="cad">CAD</button>
                <button type="button" data-mode="motor">Motor 3D</button>
                <button type="button" data-mode="mockup">Di foto</button>
            </div>
            <div class="ds-segment" aria-label="Arah pandang" data-view-group>
                <button type="button" class="is-active" data-view="iso">3D</button>
                <button type="button" data-view="side">Samping</button>
                <button type="button" data-view="top">Atas</button>
                <button type="button" data-view="back">Belakang</button>
            </div>
            <div class="ds-segment" hidden data-motor-style aria-label="Gaya motor 3D">
                <button type="button" class="is-active" data-style="vector">Vektor</button>
                <button type="button" data-style="real">Realistis</button>
            </div>
            <label class="ds-color" hidden data-motor-color title="Warna bodi motor">
                <input type="color" value="{{ $p['mockup']['body_color'] }}" data-param="mockup.body_color" aria-label="Warna bodi motor"> Bodi
            </label>
            <div class="ml-auto flex gap-2">
                <button type="button" class="btn btn-sm ds-toggle" hidden data-dims-toggle title="Tampilkan / sembunyikan label ukuran">Ukuran</button>
                <button type="button" class="btn btn-sm" data-export="png" title="Unduh gambar PNG">PNG</button>
                <button type="button" class="btn btn-sm" data-export="stl" title="Unduh model 3D (STL, satuan mm) untuk CAD / cetak 3D">STL</button>
            </div>
        </div>
        <div class="ds-source" hidden data-source-chip>
            <span><strong>Desain dari foto referensi</strong> — bentuk &amp; warna asli knalpot di foto, model bawaan disembunyikan.</span>
            <button type="button" class="btn btn-sm" data-source-reset>Pakai desain parametrik</button>
        </div>
        <div class="ds-viewport" data-viewport>
            <img class="ds-motor-photo" alt="" hidden data-motor-photo>
            <img class="ds-overlay" alt="" hidden data-overlay>
            <div class="ds-mockup-empty" hidden data-mockup-empty>
                <p class="text-[15px] font-semibold text-white">Belum ada foto motor</p>
                <p class="text-[13px] text-white/70">Unggah foto motor dari samping di tab <strong>Di motor</strong> untuk melihat knalpot ini terpasang.</p>
                <button type="button" class="btn btn-sm mt-2" data-tab-jump="mockup">Unggah foto motor</button>
            </div>
            <p class="ds-mockup-hint" hidden data-mockup-hint>Geser untuk memindah · gulir / cubit untuk memperbesar</p>
            <div class="ds-loading" data-loading>Memuat 3D…</div>
        </div>
        @if ($references)
            <div class="ds-overlay-bar">
                <label class="flex items-center gap-2"><input type="checkbox" data-overlay-toggle> Tumpuk foto referensi</label>
                <select class="ds-mini-select" data-overlay-select>
                    @foreach ($references as $i => $url)<option value="{{ $url }}">Referensi {{ $i + 1 }}</option>@endforeach
                </select>
                <input type="range" min="10" max="90" value="45" data-overlay-opacity aria-label="Transparansi referensi">
            </div>
        @endif
    </section>

    {{-- ---------------------------------------------------- hasil --}}
    <aside class="ds-result card" data-result>
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-[12px] font-medium text-muted">Perkiraan tambahan tenaga</p>
                <p class="ds-gain"><span data-r="gain_hp"></span> <small>PS</small></p>
                <p class="text-[12.5px] text-ink-soft"><span data-r="stock_hp"></span> → <strong class="text-ink" data-r="peak_hp"></strong> PS @ <span data-r="peak_rpm"></span> rpm</p>
            </div>
            <div class="ds-score" data-score><span data-r="fit.overall"></span><small>% cocok</small></div>
        </div>
        <svg class="ds-chart" viewBox="0 0 320 150" preserveAspectRatio="none" data-chart aria-label="Grafik tenaga standar vs desain"></svg>
        <div class="flex justify-between text-[11px] text-muted"><span class="flex items-center gap-1.5"><i class="ds-dot bg-slate-400"></i>Standar</span><span class="flex items-center gap-1.5"><i class="ds-dot bg-brand-500"></i>Dengan knalpot ini</span></div>

        <dl class="ds-facts">
            <div><dt>Puncak dorongan</dt><dd><span data-r="tuned_rpm"></span> rpm</dd></div>
            <div><dt>Karakter</dt><dd data-r="band"></dd></div>
            <div><dt>Leheran ideal</dt><dd><span data-r="ideal.header_length"></span> mm · Ø<span data-r="ideal.header_diameter"></span></dd></div>
            <div><dt>Volume silincer</dt><dd><span data-r="silencer_volume"></span> L (<span data-r="volume_ratio"></span>× isi)</dd></div>
            <div><dt>Perkiraan suara</dt><dd><span data-r="noise_db"></span> dB <span class="ds-chip" data-noise></span></dd></div>
        </dl>
        <div class="ds-fits">
            @foreach (['length' => 'Panjang leheran', 'diameter' => 'Diameter leheran', 'flow' => 'Aliran core', 'volume' => 'Volume silincer'] as $key => $label)
                <div><span>{{ $label }}</span><b><i data-fit="{{ $key }}"></i></b><em data-r="fit.{{ $key }}"></em></div>
            @endforeach
        </div>
        <ul class="ds-advice" data-advice></ul>
        <p class="text-[11px] leading-snug text-muted">Estimasi teoretis dari rumus tuning knalpot 4-tak, bukan hasil dyno. CVT, setelan injeksi/karburator, filter udara dan kondisi mesin ikut menentukan.</p>
    </aside>

    {{-- ---------------------------------------------------- pengaturan --}}
    <section class="ds-controls card">
        <div class="ds-tabs" role="tablist">
            @foreach (['motor' => 'Motor', 'header' => 'Leheran', 'silencer' => 'Silincer', 'finish' => 'Finishing', 'mockup' => 'Di motor', 'refs' => 'Referensi'] as $tab => $label)
                <button type="button" @class(['is-active' => $loop->first]) data-tab="{{ $tab }}">{{ $label }}@if ($tab === 'refs' && $references)<span class="ds-count">{{ count($references) }}</span>@endif @if ($tab === 'mockup' && $motorPhotos)<span class="ds-count">{{ count($motorPhotos) }}</span>@endif</button>
            @endforeach
        </div>

        <div class="ds-panel" data-panel="motor">
            <x-admin.field name="name" label="Nama desain" :value="old('name', $design->name)" required />
            <x-admin.select name="motor_select" label="Motor" :options="collect($motors)->map(fn ($m) => $m['label'])" :value="$p['motor']" :placeholder="false" data-param="motor" />
            <div class="grid grid-cols-3 gap-3" data-custom-motor @if ($p['motor'] !== 'custom') hidden @endif>
                <x-admin.field name="custom_cc" label="Isi silinder (cc)" type="number" step="0.1" :value="$p['custom_cc']" data-param="custom_cc" />
                <x-admin.field name="custom_hp" label="Tenaga std (PS)" type="number" step="0.1" :value="$p['custom_hp']" data-param="custom_hp" />
                <x-admin.field name="custom_rpm" label="@ rpm" type="number" step="100" :value="$p['custom_rpm']" data-param="custom_rpm" />
            </div>
            @include('admin.design-sims.slider', ['key' => 'target_rpm', 'label' => 'Target rpm tenaga (puncak dorongan)', 'min' => 5000, 'max' => 11000, 'step' => 250, 'unit' => 'rpm', 'value' => $p['target_rpm']])

            <div>
                <p class="label">Buat desain otomatis dari gaya</p>
                <div class="ds-styles">
                    @foreach ($styles as $key => $label)
                        <button type="button" class="ds-style" data-auto-style="{{ $key }}">@include('admin.design-sims.style-icon', ['style' => $key])<span>{{ $label }}</span></button>
                    @endforeach
                </div>
                <p class="hint">Ukuran leheran disetel ke target rpm dan silincer ±11× isi silinder. Setelah itu bebas diubah di tab Leheran & Silincer.</p>
            </div>
            <x-admin.field name="notes" label="Catatan" type="textarea" :value="old('notes', $design->notes)" placeholder="mis. untuk balap drag 201 m, pakai CVT racing" />
        </div>

        <div class="ds-panel" data-panel="header" hidden>
            <div>
                <p class="label">Bentuk tikungan</p>
                <div class="ds-chips" data-choice="header.bend">
                    @foreach (['racing' => 'Racing (S)', 'low' => 'Rendah', 'upswept' => 'Naik'] as $key => $label)
                        <button type="button" data-value="{{ $key }}" @class(['is-active' => $p['header']['bend'] === $key])>{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            @foreach ($sliders['header'] as [$key, $label, $min, $max, $step, $unit])
                @include('admin.design-sims.slider', compact('key', 'label', 'min', 'max', 'step', 'unit') + ['value' => $value($key)])
            @endforeach
        </div>

        <div class="ds-panel" data-panel="silencer" hidden>
            <div>
                <p class="label">Bentuk silincer</p>
                <div class="ds-chips" data-choice="silencer.shape">
                    @foreach ($styles as $key => $label)
                        <button type="button" data-value="{{ $key }}" @class(['is-active' => $p['silencer']['shape'] === $key])>{{ \Illuminate\Support\Str::before($label, ' (') }}</button>
                    @endforeach
                </div>
            </div>
            @foreach ($sliders['silencer'] as [$key, $label, $min, $max, $step, $unit])
                @include('admin.design-sims.slider', compact('key', 'label', 'min', 'max', 'step', 'unit') + ['value' => $value($key)])
            @endforeach
            <div>
                <p class="label">Model ujung</p>
                <div class="ds-chips" data-choice="silencer.tip">
                    @foreach ($tips as $key => $label)
                        <button type="button" data-value="{{ $key }}" @class(['is-active' => $p['silencer']['tip'] === $key])>{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            <label class="flex items-center gap-2.5 text-[13px] text-ink"><input type="checkbox" data-param="silencer.packing" @checked($p['silencer']['packing'])> Pakai peredam glasswool</label>
        </div>

        <div class="ds-panel" data-panel="finish" hidden>
            @foreach (['header.finish' => 'Finishing leheran', 'silencer.finish' => 'Finishing silincer'] as $key => $label)
                <div>
                    <p class="label">{{ $label }}</p>
                    <div class="ds-swatches" data-choice="{{ $key }}">
                        @foreach ($finishes as $finish => $finishLabel)
                            <button type="button" data-value="{{ $finish }}" @class(['is-active' => $value($key) === $finish])><i class="ds-swatch ds-swatch-{{ $finish }}"></i>{{ $finishLabel }}</button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="ds-panel" data-panel="mockup" hidden>
            <p class="ds-wide text-[12.5px] text-ink-soft">Unggah foto motor tampak samping (sisi knalpot), lalu pilih mode <strong>Di motor</strong> di atas. Geser knalpot ke posisinya, sesuaikan ukuran, sudut dan terangnya supaya terlihat seperti sudah terpasang sebelum diproduksi.</p>
            @if ($motorPhotos)
                <div class="ds-refs ds-wide" data-photo-choices>
                    @foreach ($design->motor_photos as $i => $path)
                        <label class="ds-ref">
                            <button type="button" class="block w-full" data-photo-pick="{{ $i }}"><img src="{{ asset($path) }}" alt="Foto motor {{ $i + 1 }}" loading="lazy"></button>
                            <span><input type="checkbox" name="remove_motor_photos[]" value="{{ $path }}"> Hapus</span>
                        </label>
                    @endforeach
                </div>
            @endif
            <div class="ds-wide">
                <input type="file" name="motor_photos[]" accept="image/*" multiple class="input" data-motor-input>
                <p class="hint">Foto baru langsung bisa dicoba; tekan Simpan desain agar tersimpan.</p>
                @error('motor_photos')<p class="error">{{ $message }}</p>@enderror
                @error('motor_photos.*')<p class="error">{{ $message }}</p>@enderror
            </div>
            @include('admin.design-sims.slider', ['key' => 'mockup.scale', 'label' => 'Ukuran knalpot di foto', 'min' => 0.1, 'max' => 1.5, 'step' => 0.01, 'unit' => '×', 'value' => $p['mockup']['scale']])
            @include('admin.design-sims.slider', ['key' => 'mockup.rotation', 'label' => 'Kemiringan', 'min' => -30, 'max' => 30, 'step' => 0.5, 'unit' => '°', 'value' => $p['mockup']['rotation']])
            @include('admin.design-sims.slider', ['key' => 'mockup.brightness', 'label' => 'Terang knalpot (samakan dengan foto)', 'min' => 0.4, 'max' => 1.8, 'step' => 0.05, 'unit' => '×', 'value' => $p['mockup']['brightness']])
            <div class="flex flex-wrap items-end gap-2">
                <label class="flex h-10 items-center gap-2 text-[13px] text-ink"><input type="checkbox" data-param="mockup.flip" @checked($p['mockup']['flip'])> Balik arah (knalpot di sisi kiri foto)</label>
            </div>
            <div class="ds-wide flex flex-wrap items-center gap-2">
                <button type="button" class="btn btn-sm btn-primary" data-auto-place>Pasang otomatis</button>
                <button type="button" class="btn btn-sm" data-tap-place>Pasang dengan 2 ketukan</button>
                <button type="button" class="btn btn-sm" data-mockup-reset>Atur ulang posisi</button>
                <p class="w-full text-[12px] text-muted" data-auto-status>Saat foto dipilih, roda motor dideteksi lalu knalpot dipasang otomatis di samping roda belakang dengan skala sesuai ukuran ban.</p>
            </div>
            <div class="ds-wide flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-primary" data-export="mockup">Unduh gambar di motor</button>
            </div>
        </div>

        <div class="ds-panel" data-panel="refs" hidden>
            <p class="ds-wide text-[12.5px] text-ink-soft">Unggah foto knalpot contoh (tampak samping, latar polos lebih akurat), lalu tekan <strong>Terapkan</strong>: bentuk, proporsi silincer dan warnanya dibaca dari foto lalu diterapkan ke desain.</p>
            <p class="ds-wide text-[12px] font-medium text-brand-700" data-apply-status hidden></p>
            @if ($references)
                <div class="ds-refs">
                    @foreach ($design->references as $i => $path)
                        <div class="ds-ref">
                            <img src="{{ asset($path) }}" alt="Referensi {{ $i + 1 }}" loading="lazy">
                            <span class="justify-between">
                                <label class="flex items-center gap-1.5"><input type="checkbox" name="remove_references[]" value="{{ $path }}"> Hapus</label>
                                <button type="button" class="ds-apply" data-apply-ref="{{ asset($path) }}">Terapkan</button>
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
            <input type="file" name="references[]" accept="image/*" multiple class="input" data-ref-input>
            <div class="ds-refs" data-ref-preview></div>
            @error('references')<p class="error">{{ $message }}</p>@enderror
            @error('references.*')<p class="error">{{ $message }}</p>@enderror

            <div class="ds-ai">
                <div>
                    <p class="text-[13px] font-semibold text-ink">Saran ukuran dari foto (AI)</p>
                    <p class="text-[12px] text-muted">Membaca foto referensi lalu mengisi bentuk & ukuran awal otomatis. Aktif setelah integrasi Claude API dipasang dan API key diisi.</p>
                </div>
                <button type="button" class="btn btn-sm" disabled title="Belum aktif: butuh API key Anthropic">Belum aktif</button>
            </div>
        </div>
    </section>
</form>
@endsection

@push('scripts')
    @vite('resources/js/design-sim.js')
@endpush
