<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function customers(): JsonResponse
    {
        return response()->json([
            'data' => User::query()
                ->where('role', 'customer')
                ->latest()
                ->get()
                ->map(fn (User $user) => $this->customerData($user)),
        ]);
    }

    public function customer(User $user): JsonResponse
    {
        abort_unless($user->role === 'customer', 404);

        return response()->json(['data' => $this->customerData($user)]);
    }

    public function couriers(): JsonResponse
    {
        return response()->json([
            'data' => User::query()
                ->where('role', 'courier')
                ->latest()
                ->get()
                ->map(fn (User $user) => $this->courierData($user)),
        ]);
    }

    public function courier(User $user): JsonResponse
    {
        abort_unless($user->role === 'courier', 404);

        return response()->json(['data' => $this->courierData($user)]);
    }

    private function customerData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
        ];
    }

    private function courierData(User $user): array
    {
        return [
            ...$this->customerData($user),
            'vehicle' => $user->vehicle,
        ];
    }
}
