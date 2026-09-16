<x-guest-layout>
    <div class="mb-6">
        <p class="text-sm font-bold uppercase tracking-[0.2em] text-red-600">Admin Yojek</p>
        <h2 class="mt-2 text-2xl font-black text-slate-950">Sign in admin</h2>
        <p class="mt-2 text-sm text-slate-600">Masuk ke dashboard operasional Yojek.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('admin.login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Username')" />
            <x-text-input id="email" class="mt-1 block w-full rounded-xl" type="text" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-1 block w-full rounded-xl" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex justify-end">
            <x-primary-button class="rounded-xl bg-red-600 px-5 py-3 hover:bg-red-700">
                {{ __('Sign in') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
