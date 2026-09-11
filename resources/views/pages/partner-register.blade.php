@extends('layouts.app')

@section('title', 'Jadi Partner')

@section('content')
    <div class="no-bottom no-top" id="content">
        <div id="top"></div>

        <section class="bg-dark text-light relative jarallax">
            <div class="de-gradient-edge-top"></div>
            <div class="container relative z-2">
                <div class="row gy-4 justify-content-center">
                    <div class="col-lg-8 text-center">
                        <div class="spacer-double sm-hide"></div>
                        <h1 class="mb-3">Jadi Partner</h1>
                        <p class="lead mb-0">
                            Punya toko knalpot sendiri dengan halaman toko dan panel admin seperti ini.
                            Isi datanya, lalu tunggu persetujuan kami.
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

                        @if (session('partner_terkirim'))
                            {{-- Yang ditunggu orang sesudah menekan kirim adalah kepastian bahwa
                                 formulirnya sampai, dan apa yang terjadi berikutnya. --}}
                            <div class="p-4 mb-4 rounded" style="background:#1f3d2b;border:1px solid #2f7d4f">
                                <h4 class="mb-2">Pendaftaran terkirim</h4>
                                <p class="mb-2">
                                    Toko <strong>{{ session('partner_terkirim') }}</strong> sudah masuk ke antrean
                                    persetujuan. Kami kabari lewat email begitu disetujui.
                                </p>
                                <p class="mb-0 text-muted">
                                    Sandi yang Anda isi tadi akan jadi sandi masuk panel admin toko Anda.
                                </p>
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="p-4 mb-4 rounded" style="background:#3d1f1f;border:1px solid #7d2f2f">
                                <strong>Ada yang perlu diperbaiki:</strong>
                                <ul class="mb-0 mt-2">
                                    @foreach ($errors->all() as $pesan)
                                        <li>{{ $pesan }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form action="{{ route('partner.register.store') }}" method="POST">
                            @csrf

                            <h4 class="mb-3">Toko Anda</h4>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Nama Toko</label>
                                    <input type="text" name="name" class="form-control"
                                        value="{{ old('name') }}" placeholder="Knalpot Jaya Motor" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Alamat Toko</label>
                                    <div class="input-group">
                                        <input type="text" name="slug" class="form-control"
                                            value="{{ old('slug') }}" placeholder="knalpot-jaya" required>
                                        <span class="input-group-text">.{{ config('app.partner_domain') }}</span>
                                    </div>
                                    <small class="text-muted">
                                        Huruf kecil, angka, dan tanda hubung. Ini jadi alamat toko Anda.
                                    </small>
                                </div>
                            </div>

                            <h4 class="mb-3">Data Anda</h4>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Nama Lengkap</label>
                                    <input type="text" name="owner_name" class="form-control"
                                        value="{{ old('owner_name') }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nomor WhatsApp</label>
                                    <input type="text" name="owner_phone" class="form-control"
                                        value="{{ old('owner_phone') }}" placeholder="08xx">
                                </div>
                            </div>

                            <h4 class="mb-1">Akun Panel Admin</h4>
                            <p class="text-muted mb-3">Dipakai untuk masuk ke panel toko Anda setelah disetujui.</p>

                            <div class="row g-3 mb-4">
                                <div class="col-md-12">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="owner_email" class="form-control"
                                        value="{{ old('owner_email') }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Sandi</label>
                                    <input type="password" name="password" class="form-control"
                                        minlength="8" required>
                                    <small class="text-muted">Minimal 8 karakter.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Ulangi Sandi</label>
                                    <input type="password" name="password_confirmation" class="form-control"
                                        minlength="8" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Ceritakan sedikit tentang usaha Anda</label>
                                <textarea name="notes" class="form-control" rows="3"
                                    placeholder="Bengkel knalpot custom di Malang, sudah jalan 3 tahun.">{{ old('notes') }}</textarea>
                            </div>

                            <button type="submit" class="btn-main">Kirim Pendaftaran</button>
                        </form>

                        <div class="spacer-double"></div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
