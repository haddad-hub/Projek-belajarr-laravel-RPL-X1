@php
    $slug = str($user->name)->lower()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-')->toString();
    $username = $slug !== '' ? $slug : 'user-'.$user->id;
    $target = $user->role === 'courier'
        ? '/Yojek/app%20kurir%20yojek.html'
        : '/Yojek/app%20pelanggan%20yojek.html';
    $profile = [
        'id' => $user->id,
        'username' => $username,
        'name' => $user->name,
        'phone' => $user->phone,
        'address' => $user->address,
        'vehicle' => $user->vehicle ?: 'Motor',
        'photo' => '',
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Membuka Yojek</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-950 text-white">
        <main class="flex min-h-screen items-center justify-center px-6">
            <div class="text-center">
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-red-300">Yojek</p>
                <h1 class="mt-3 text-2xl font-bold">Menyiapkan akun {{ $user->role === 'courier' ? 'kurir' : 'pelanggan' }}</h1>
                <p class="mt-2 text-sm text-slate-300">Sebentar, kamu akan diarahkan otomatis.</p>
            </div>
        </main>

        <script>
            const role = @json($user->role);
            const username = @json($username);
            const profile = @json($profile);
            const target = @json($target);

            if (role === 'courier') {
                const profileKey = 'yojek_courier_profiles_v3';
                const activeKey = 'yojek_courier_active_username_v3';
                const profiles = JSON.parse(localStorage.getItem(profileKey) || '{}');
                profiles[username] = {...profiles[username], ...profile};
                localStorage.setItem(profileKey, JSON.stringify(profiles));
                localStorage.setItem(activeKey, username);
            } else {
                const profileKey = 'yojek_customer_profiles_v3';
                const activeKey = 'yojek_customer_active_username_v3';
                const profiles = JSON.parse(localStorage.getItem(profileKey) || '{}');
                profiles[username] = {...profiles[username], ...profile};
                localStorage.setItem(profileKey, JSON.stringify(profiles));
                localStorage.setItem(activeKey, username);
            }

            window.location.href = target;
        </script>
    </body>
</html>
