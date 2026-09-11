{{--
    Banner iklan untuk satu posisi.

    Dipanggil: @include('partials.iklan', ['posisi' => 'footer'])

    Tautannya lewat route redirect, bukan langsung ke situs pemasang — supaya
    kliknya terhitung tanpa JavaScript, yang di halaman beriklan justru paling
    sering diblokir.
--}}
@php($daftarIklan = \App\Models\Advertisement::untuk($posisi))

@if ($daftarIklan->isNotEmpty())
    <div class="iklan-blok iklan-{{ $posisi }}">
        @foreach ($daftarIklan as $iklan)
            <a href="{{ route('iklan.klik', $iklan) }}"
                target="_blank"
                rel="noopener sponsored"
                class="iklan-tautan"
                aria-label="Iklan: {{ $iklan->title }}">
                <img src="{{ asset('storage/'.$iklan->image_path) }}"
                    alt="{{ $iklan->title }}"
                    loading="lazy">
            </a>
        @endforeach
    </div>
@endif
