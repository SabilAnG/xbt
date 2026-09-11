@extends('layouts.app')

@section('title', 'Pasang Iklan')

@section('content')
    <div class="no-bottom no-top" id="content">
        <div id="top"></div>

        <section class="bg-dark text-light relative jarallax">
            <div class="de-gradient-edge-top"></div>
            <div class="container relative z-2">
                <div class="row gy-4 justify-content-center">
                    <div class="col-lg-8 text-center">
                        <div class="spacer-double sm-hide"></div>
                        <h1 class="mb-3">Pasang Iklan</h1>
                        <p class="lead mb-0">
                            Banner Anda tampil di halaman yang dikunjungi pencari knalpot setiap hari.
                        </p>
                        <div class="spacer-single"></div>
                    </div>
                </div>
            </div>
            <div class="de-gradient-edge-bottom"></div>
        </section>

        <section>
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8">

                        <h4 class="mb-3">Posisi yang tersedia</h4>
                        <ul class="mb-5">
                            @foreach (\App\Models\Advertisement::POSITIONS as $keterangan)
                                <li class="mb-2">{{ $keterangan }}</li>
                            @endforeach
                        </ul>

                        <h4 class="mb-3">Cara memasang</h4>
                        <ol class="mb-5">
                            <li class="mb-2">Hubungi kami lewat tombol di bawah.</li>
                            <li class="mb-2">
                                Kirim foto banner Anda — bentuk melintang, paling enak dilihat pada rasio 4:1,
                                dan alamat yang dituju saat banner diklik.
                            </li>
                            <li class="mb-2">Sebutkan posisi yang Anda mau dan berapa lama ingin tayang.</li>
                            <li>Banner kami pasang, dan Anda bisa minta laporan jumlah kliknya kapan saja.</li>
                        </ol>

                        @php
                            $nomor = preg_replace('/\D/', '', (string) config('app.admin_whatsapp'));
                            $pesan = rawurlencode('Halo, saya ingin memasang iklan di website Hypersonic Speed Tech.');
                        @endphp

                        <a href="https://wa.me/{{ $nomor }}?text={{ $pesan }}"
                            target="_blank" rel="noopener" class="btn-main">
                            Hubungi Lewat WhatsApp
                        </a>

                        <div class="spacer-double"></div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
