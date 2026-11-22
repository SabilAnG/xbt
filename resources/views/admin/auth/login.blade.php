<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite('resources/css/admin.css')
</head>
<body class="grid min-h-screen place-items-center bg-zinc-900 p-4 font-sans antialiased">
    <div class="w-full max-w-sm">
        <div class="mb-6 flex items-center justify-center gap-3 text-white">
            <span class="grid size-10 place-items-center rounded-lg bg-brand-600 text-sm font-bold">XBT</span>
            <div>
                <p class="font-semibold">Knalpot Admin</p>
                <p class="text-xs text-zinc-400">Manajemen Produksi &amp; Penjualan</p>
            </div>
        </div>

        <form method="POST" action="{{ route('login.attempt') }}" class="card space-y-4 p-6">
            @csrf
            <x-admin.field name="email" label="Email" type="email" required autofocus autocomplete="username" />
            <x-admin.field name="password" label="Password" type="password" required autocomplete="current-password" />
            <label class="flex items-center gap-2 text-sm text-zinc-600">
                <input type="checkbox" name="remember" value="1" class="rounded border-zinc-300"> Ingat saya
            </label>
            <button class="btn btn-primary w-full">Masuk</button>
        </form>
    </div>
</body>
</html>
