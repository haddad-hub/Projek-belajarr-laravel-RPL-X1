<x-guest-layout>
    <div class="mb-6">
        <p class="text-sm font-bold uppercase tracking-[0.2em] text-pink-600">Login Bonjek</p>
        <h2 class="mt-2 text-2xl font-black text-slate-950">Pilih akses akun</h2>
        <p class="mt-2 text-sm text-slate-600">Admin: username <strong>adminbonjek</strong>, sandi <strong>admin123</strong>.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="role" :value="__('Masuk sebagai')" />
            <select id="role" name="role" class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-pink-500 focus:ring-pink-500">
                <option value="customer" @selected(old('role') === 'customer')>Pelanggan</option>
                <option value="courier" @selected(old('role') === 'courier')>Kurir</option>
                <option value="admin" @selected(old('role') === 'admin')>Admin Dashboard</option>
            </select>
            <x-input-error :messages="$errors->get('role')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email / Username Admin')" />
            <x-text-input id="email" class="mt-1 block w-full rounded-xl" type="text" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-1 block w-full rounded-xl" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="inline-flex items-center">
            <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-pink-600 shadow-sm focus:ring-pink-500" name="remember">
            <span class="ms-2 text-sm text-slate-600">{{ __('Remember me') }}</span>
        </label>

        <div class="flex items-center justify-between gap-4">
            <a class="text-sm font-semibold text-pink-700 underline hover:text-pink-900" href="{{ route('register') }}">
                Buat akun
            </a>

            <x-primary-button class="rounded-xl bg-slate-950 px-5 py-3 hover:bg-pink-700">
                {{ __('Masuk') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
