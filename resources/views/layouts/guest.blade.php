<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Bonjek</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-900 antialiased">
        <div class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(255,122,184,.24),transparent_30%),linear-gradient(115deg,#fff7ed_0%,#fdf2f8_44%,#eff6ff_100%)] px-4 py-8">
            <div class="mx-auto flex min-h-[calc(100vh-4rem)] w-full max-w-6xl items-center justify-center">
                <div class="hidden flex-1 pr-10 lg:block">
                    <div class="inline-flex items-center gap-3">
                        <img class="h-14 w-14 rounded-2xl bg-white p-2 shadow-sm" src="/Bonjek/logo.png" alt="Logo Bonjek">
                        <div>
                            <p class="text-sm font-extrabold uppercase tracking-[0.24em] text-pink-600">Bonjek</p>
                            <h1 class="text-4xl font-black text-slate-950">Masuk ke layananmu</h1>
                        </div>
                    </div>
                    @if (request()->routeIs('admin.login'))
                        <p class="mt-6 max-w-xl text-lg leading-8 text-slate-700">
                            Kelola operasional dan dashboard Bonjek melalui akses administrator.
                        </p>
                    @else
                        <p class="mt-6 max-w-xl text-lg leading-8 text-slate-700">
                            Akses pelanggan dan kurir tersambung langsung ke aplikasi Bonjek.
                        </p>
                        <div class="mt-8 grid max-w-xl grid-cols-2 gap-3 text-sm font-semibold">
                            <div class="rounded-xl bg-white/75 p-4 text-center shadow-sm ring-1 ring-white/80">Pelanggan</div>
                            <div class="rounded-xl bg-white/75 p-4 text-center shadow-sm ring-1 ring-white/80">Kurir</div>
                        </div>
                    @endif
                </div>

                <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white/90 px-6 py-6 shadow-2xl shadow-pink-200/50 ring-1 ring-white/80 backdrop-blur">
                    <div class="mb-6 flex items-center gap-3 lg:hidden">
                        <img class="h-12 w-12 rounded-2xl bg-white p-2 shadow-sm" src="/Bonjek/logo.png" alt="Logo Bonjek">
                        <div>
                            <p class="text-xs font-extrabold uppercase tracking-[0.2em] text-pink-600">Bonjek</p>
                            <h1 class="text-xl font-black text-slate-950">Akses Akun</h1>
                        </div>
                    </div>
                {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
