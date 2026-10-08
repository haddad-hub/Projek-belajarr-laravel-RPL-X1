<x-guest-layout>
    <div class="mb-6">
        <p class="text-sm font-bold uppercase tracking-[0.2em] text-red-600">Login Yojek</p>
        <h2 class="mt-2 text-2xl font-black text-slate-950">Masuk pelanggan atau kurir</h2>
        
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <p id="login-error" class="mb-4 hidden rounded-lg bg-red-50 p-3 text-sm font-semibold text-red-700" role="alert"></p>

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="role" :value="__('Masuk sebagai')" />
            <select id="role" name="role" class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500">
                <option value="customer" @selected(old('role', $role) === 'customer')>Pelanggan</option>
                <option value="courier" @selected(old('role', $role) === 'courier')>Kurir</option>
            </select>
            <x-input-error :messages="$errors->get('role')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1 block w-full rounded-xl" type="text" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-1 block w-full rounded-xl" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="inline-flex items-center">
            <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-red-600 shadow-sm focus:ring-red-500" name="remember">
            <span class="ms-2 text-sm text-slate-600">{{ __('Remember me') }}</span>
        </label>

        <div class="flex items-center justify-between gap-4">
            <a class="text-sm font-semibold text-red-700 underline hover:text-red-900" href="{{ route('register') }}">
                Buat akun
            </a>

            <x-primary-button class="rounded-xl bg-red-600 px-5 py-3 hover:bg-red-700">
                {{ __('Masuk') }}
            </x-primary-button>
        </div>
    </form>

    <script>
        const loginForm = document.querySelector('form');
        loginForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const errorBox = document.getElementById('login-error');
            errorBox.classList.add('hidden');

            const xsrfCookie = document.cookie.split(';').find((cookie) => cookie.trim().startsWith('XSRF-TOKEN='));
            const xsrfToken = xsrfCookie ? decodeURIComponent(xsrfCookie.trim().slice('XSRF-TOKEN='.length)) : '';

            try {
                const response = await fetch(@json(url('/api/yojek/login')), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
                    },
                    body: JSON.stringify(Object.fromEntries(new FormData(loginForm))),
                });
                const result = await response.json();
                if (!response.ok) {
                    throw new Error(result.message || Object.values(result.errors || {}).flat()[0] || 'Login gagal.');
                }

                sessionStorage.removeItem('yojek_token_customer_v1');
                sessionStorage.removeItem('yojek_token_courier_v1');
                sessionStorage.setItem(
                    result.role === 'courier' ? 'yojek_token_courier_v1' : 'yojek_token_customer_v1',
                    result.token,
                );
                window.location.assign(result.role === 'courier'
                    ? @json(url('/Yojek/app kurir yojek.html'))
                    : @json(url('/Yojek/app pelanggan yojek.html')));
            } catch (error) {
                errorBox.textContent = error.message;
                errorBox.classList.remove('hidden');
            }
        });
    </script>
</x-guest-layout>
