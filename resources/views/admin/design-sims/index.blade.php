@extends('layouts.admin', ['title' => 'Simulasi Desain', 'subtitle' => 'Desain knalpot 3D dari ukuran & foto referensi, lengkap dengan estimasi tambahan tenaga.'])

@section('actions')
    <a href="{{ route('admin.design-sims.create') }}" class="btn btn-primary">+ Desain baru</a>
@endsection

@section('content')
    {{-- Mulai cepat: motor + gaya → editor langsung berisi desain otomatis. --}}
    <form method="GET" action="{{ route('admin.design-sims.create') }}" class="card grid gap-4 p-5 lg:grid-cols-[minmax(0,260px)_minmax(0,1fr)_auto] lg:items-end">
        <x-admin.select name="motor" label="Motor" :options="collect($motors)->map(fn ($m) => $m['label'])" value="mio" :placeholder="false" />
        <div>
            <p class="label">Gaya knalpot</p>
            <div class="ds-styles">
                @foreach ($styles as $key => $label)
                    <label class="ds-style has-[:checked]:is-active">
                        <input type="radio" name="gaya" value="{{ $key }}" class="sr-only" @checked($loop->first)>
                        @include('admin.design-sims.style-icon', ['style' => $key])<span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>
        <button class="btn btn-primary">Buat desain otomatis</button>
    </form>

    @if ($designs->isEmpty())
        <div class="card p-10 text-center">
            <p class="text-[15px] font-semibold text-ink">Belum ada desain</p>
            <p class="mt-1 text-[13px] text-muted">Pilih motor & gaya di atas, lalu sesuaikan ukuran leheran dan silincernya.</p>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($designs as $design)
                @php $r = $design->result(); @endphp
                <a href="{{ route('admin.design-sims.edit', $design) }}" class="card group overflow-hidden transition hover:-translate-y-0.5 hover:shadow-(--shadow-pop)">
                    <div class="ds-thumb">
                        @if ($design->thumbnail)
                            <img src="{{ asset($design->thumbnail) }}" alt="" loading="lazy">
                        @else
                            @include('admin.design-sims.style-icon', ['style' => $design->params['silencer']['shape'] ?? 'gp'])
                        @endif
                        <span class="ds-thumb-badge">{{ $r['gain_hp'] >= 0 ? '+' : '' }}{{ number_format($r['gain_hp'], 2, ',', '.') }} PS</span>
                    </div>
                    <div class="space-y-1 p-4">
                        <p class="truncate text-[14.5px] font-semibold text-ink">{{ $design->name }}</p>
                        <p class="truncate text-[12.5px] text-muted">{{ $design->motorLabel() }} · {{ $styles[$design->params['silencer']['shape'] ?? 'gp'] ?? '' }}</p>
                        <p class="text-[12px] text-ink-soft">Leheran {{ (int) $design->params['header']['length'] }} mm Ø{{ $design->params['header']['diameter'] }} · silincer {{ (int) $design->params['silencer']['length'] }} mm · {{ $r['fit']['overall'] }}% cocok</p>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection
