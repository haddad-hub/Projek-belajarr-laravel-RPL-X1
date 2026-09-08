@php
    $slug = str($user->name)->lower()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-')->toString();
    $username = $slug !== '' ? $slug : 'user-'.$user->id;
    $target = $user->role === 'courier'
        ? '/Bonjek/app%20kurir%20bonjek.html'
        : '/Bonjek/app%20pelanggan%20bonjek.html';
    $profile = [
        'id' => ($user->role === 'courier' ? 'KURIR-' : 'CUST-').strtoupper($username),
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
        <title>Membuka Bonjek</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-950 text-white">
        <main class="flex min-h-screen items-center justify-center px-6">
            <div class="text-center">
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-pink-300">Bonjek</p>
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
                const profileKey = 'bonjek_courier_profiles_v2';
                const activeKey = 'bonjek_courier_active_username_v2';
                const profiles = JSON.parse(localStorage.getItem(profileKey) || '{}');
                profiles[username] = {...profiles[username], ...profile};
                localStorage.setItem(profileKey, JSON.stringify(profiles));
                localStorage.setItem(activeKey, username);
            } else {
                const profileKey = 'bonjek_customer_profiles_v2';
                const activeKey = 'bonjek_customer_active_username_v2';
                const profiles = JSON.parse(localStorage.getItem(profileKey) || '{}');
                profiles[username] = {...profiles[username], ...profile};
                localStorage.setItem(profileKey, JSON.stringify(profiles));
                localStorage.setItem(activeKey, username);
            }

            window.location.href = target;
        </script>
    </body>
</html>
