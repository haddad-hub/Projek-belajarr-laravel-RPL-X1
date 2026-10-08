<x-guest-layout>
    <div class="mb-6">
        <p class="text-sm font-bold uppercase tracking-[0.2em] text-red-600">Register Yojek</p>
        <h2 class="mt-2 text-2xl font-black text-slate-950">Buat akun operasional</h2>
        <p class="mt-2 text-sm text-slate-600">Data ini langsung dipakai sebagai profil di aplikasi pelanggan atau kurir.</p>
    </div>

    <p id="register-error" class="mb-4 hidden rounded-lg bg-red-50 p-3 text-sm font-semibold text-red-700" role="alert"></p>

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="role" :value="__('Daftar sebagai')" />
            <select id="role" name="role" class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500">
                <option value="customer" @selected(old('role') === 'customer')>Pelanggan</option>
                <option value="courier" @selected(old('role') === 'courier')>Kurir</option>
            </select>
            <x-input-error :messages="$errors->get('role')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="name" :value="__('Nama')" />
            <x-text-input id="name" class="mt-1 block w-full rounded-xl" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1 block w-full rounded-xl" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="phone" :value="__('Nomor HP')" />
            <x-text-input id="phone" class="mt-1 block w-full rounded-xl" type="text" name="phone" :value="old('phone')" required autocomplete="tel" />
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="address" :value="__('Alamat')" />
            <x-text-input id="address" class="mt-1 block w-full rounded-xl" type="text" name="address" :value="old('address')" required autocomplete="street-address" />
            <x-input-error :messages="$errors->get('address')" class="mt-2" />
        </div>

        <div id="vehicle-field" class="{{ old('role', 'customer') === 'courier' ? '' : 'hidden' }}">
            <x-input-label for="vehicle" :value="__('Kendaraan kurir')" />
            <x-text-input id="vehicle" class="mt-1 block w-full rounded-xl" type="text" name="vehicle" :value="old('vehicle', 'Motor')" autocomplete="off" />
            <p class="mt-1 text-xs text-slate-500">Diisi kalau daftar sebagai kurir.</p>
            <x-input-error :messages="$errors->get('vehicle')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-1 block w-full rounded-xl" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Konfirmasi Password')" />
            <x-text-input id="password_confirmation" class="mt-1 block w-full rounded-xl" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between gap-4">
            <a class="text-sm font-semibold text-red-700 underline hover:text-red-900" href="{{ route('login') }}">
                Sudah punya akun?
            </a>

            <x-primary-button class="rounded-xl bg-red-600 px-5 py-3 hover:bg-red-700">
                {{ __('Daftar') }}
            </x-primary-button>
        </div>
    </form>

    <script>
        const roleSelect = document.getElementById('role');
        const vehicleField = document.getElementById('vehicle-field');

        function toggleVehicleField() {
            const isCourier = roleSelect.value === 'courier';
            vehicleField.classList.toggle('hidden', !isCourier);
            document.getElementById('vehicle').disabled = !isCourier;
        }

        roleSelect.addEventListener('change', toggleVehicleField);
        toggleVehicleField();

        const registerForm = document.querySelector('form');
        registerForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const errorBox = document.getElementById('register-error');
            errorBox.classList.add('hidden');

            const xsrfCookie = document.cookie.split(';').find((cookie) => cookie.trim().startsWith('XSRF-TOKEN='));
            const xsrfToken = xsrfCookie ? decodeURIComponent(xsrfCookie.trim().slice('XSRF-TOKEN='.length)) : '';

            try {
                const response = await fetch(@json(url('/api/yojek/register')), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
                    },
                    body: JSON.stringify(Object.fromEntries(new FormData(registerForm))),
                });
                const result = await response.json();
                if (!response.ok) {
                    throw new Error(result.message || Object.values(result.errors || {}).flat()[0] || 'Pendaftaran gagal.');
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
