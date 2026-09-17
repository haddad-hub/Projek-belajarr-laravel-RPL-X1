<?php

namespace App\Http\Controllers\Api;

use App\Models\FinanceEntry;
use App\Models\Order;
use App\Models\User;
use App\Models\YojekSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class YojekController
{
    public function issueToken(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor, 401);
        return response()->json(['token' => $this->createToken($actor instanceof User ? $actor->id : null, $actor instanceof User ? $actor->role : 'admin')]);
    }

    public function state(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);
        $user = $this->actor($request);
        if (is_array($user)) $user = null;
        $orders = Order::query()->with(['customer', 'courier'])->latest()->get();

        if ($user?->role === 'customer') {
            $orders = $orders->where('customer_user_id', $user->id)->values();
        } elseif ($user?->role === 'courier') {
            $orders = $orders->filter(fn (Order $order): bool =>
                $order->courier_user_id === $user->id || $order->status === 'new'
            )->values();
        }

        return response()->json([
            'actor' => $this->actorData($request),
            'orders' => $orders->map(fn (Order $order): array => $this->orderData($order)),
            'finance' => $this->isAdmin($request)
                ? FinanceEntry::query()->latest()->get()->map(fn (FinanceEntry $entry): array => $this->financeData($entry))
                : [],
            // Customers only need the courier name already embedded in their
            // own order. Attendance data and courier management stay private
            // to the courier concerned and the administrator.
            'couriers' => User::query()
                ->where('role', 'courier')
                ->when(! $this->isAdmin($request), fn ($query) => $query->whereKey($user?->role === 'courier' ? $user->id : 0))
                ->latest()
                ->get()
                ->map(fn (User $courier): array => $this->courierData($courier)),
            'syncedAt' => now()->toISOString(),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $rules = [
            'phone' => ['nullable', 'string', 'max:30'],
        ];

        if ($user->role === 'customer') {
            $rules['address'] = ['nullable', 'string', 'max:255'];
        } elseif ($user->role === 'courier') {
            $rules['vehicle'] = ['nullable', 'string', 'max:50'];
        }

        $data = $request->validate($rules);
        $user->fill($data)->save();

        return response()->json(['actor' => $this->actorData($request)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $this->token($request);
        if ($token) {
            YojekSession::query()->where('token_hash', hash('sha256', $token))->delete();
        } else {
            $this->authorizeAccess($request);
            Auth::guard('web')->logout();
        }

        return response()->json(['ok' => true]);
    }

    public function createOrder(Request $request): JsonResponse
    {
        $user = $this->requireRole($request, 'customer');
        $data = $request->validate([
            'item' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:50'],
            'qty' => ['required', 'integer', 'min:1'],
            'pickup' => ['required', 'string', 'max:255'],
            'dropoff' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = Order::create([
            ...$data,
            'customer_user_id' => $user->id,
            'customer_username' => $this->username($user),
            'customer_name' => $user->name,
            'customer_phone' => $user->phone,
            'status' => 'new',
            'revenue' => 0,
        ]);

        return response()->json(['order' => $this->orderData($order->load(['customer', 'courier']))], 201);
    }

    public function acceptOrder(Request $request, Order $order): JsonResponse
    {
        $courier = $this->requireRole($request, 'courier');
        if ($courier->admin_disabled === true || $courier->is_active === false) {
            throw ValidationException::withMessages(['courier' => 'Status Anda sedang dinonaktifkan oleh admin atau belum aktif.']);
        }
        $order = DB::transaction(function () use ($order, $courier): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status !== 'new' || $locked->courier_user_id !== null) {
                throw ValidationException::withMessages(['order' => 'Pesanan sudah diambil kurir lain.']);
            }
            $locked->update(['courier_user_id' => $courier->id, 'status' => 'accepted', 'accepted_at' => now()]);
            return $locked->load(['customer', 'courier']);
        });

        return response()->json(['order' => $this->orderData($order)]);
    }

    public function updateAvailability(Request $request): JsonResponse
    {
        $courier = $this->requireRole($request, 'courier');
        $data = $request->validate([
            'isActive' => ['required', 'boolean'],
            'attendancePhoto' => ['nullable', 'string'],
            'attendanceLocation' => ['nullable', 'array'],
            'attendanceLocation.latitude' => ['nullable', 'numeric'],
            'attendanceLocation.longitude' => ['nullable', 'numeric'],
            'attendanceLocation.accuracy' => ['nullable', 'numeric'],
        ]);
        if ($data['isActive'] && $courier->admin_disabled) {
            throw ValidationException::withMessages(['courier' => 'Status Anda sedang dinonaktifkan oleh admin.']);
        }
        $courier->update([
            'is_active' => $data['isActive'],
            'attendance_photo' => $data['attendancePhoto'] ?? $courier->attendance_photo,
            'attendance_at' => ! empty($data['attendancePhoto']) ? now() : $courier->attendance_at,
            'attendance_location' => $data['attendanceLocation'] ?? $courier->attendance_location,
        ]);

        return response()->json(['courier' => $this->courierData($courier->fresh())]);
    }

    public function setCourierDisabled(Request $request, User $user): JsonResponse
    {
        abort_unless($this->isAdmin($request), 403);
        abort_unless($user->role === 'courier', 404);
        $disabled = $request->boolean('disabled');
        $user->update(['admin_disabled' => $disabled, 'is_active' => ! $disabled]);

        return response()->json(['courier' => $this->courierData($user->fresh())]);
    }

    public function completeOrder(Request $request, Order $order): JsonResponse
    {
        $courier = $this->requireRole($request, 'courier');
        abort_unless($order->courier_user_id === $courier->id, 403);
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99']]);
        abort_unless($order->status === 'accepted', 422, 'Pesanan tidak sedang diantar.');
        $order->update(['status' => 'awaiting_confirmation', 'revenue' => $data['amount'], 'arrived_at' => now()]);

        return response()->json(['order' => $this->orderData($order->load(['customer', 'courier']))]);
    }

    public function confirmOrder(Request $request, Order $order): JsonResponse
    {
        $customer = $this->requireRole($request, 'customer');
        abort_unless($order->customer_user_id === $customer->id, 403);
        DB::transaction(function () use ($order, $customer): void {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless($locked->status === 'awaiting_confirmation', 422, 'Pesanan belum bisa dikonfirmasi.');
            $locked->update(['status' => 'completed', 'completed_at' => now()]);
            FinanceEntry::query()->firstOrCreate(
                ['order_id' => $locked->id, 'type' => 'income'],
                ['category' => 'Pembayaran Order', 'amount' => $locked->revenue, 'note' => 'Pembayaran '.$locked->item.' oleh '.$customer->name, 'created_by_user_id' => $customer->id],
            );
        });

        return response()->json(['order' => $this->orderData($order->fresh()->load(['customer', 'courier']))]);
    }

    public function rateOrder(Request $request, Order $order): JsonResponse
    {
        $customer = $this->requireRole($request, 'customer');
        abort_unless($order->customer_user_id === $customer->id, 403);
        $data = $request->validate(['rating' => ['required', 'integer', 'min:1', 'max:5']]);
        abort_unless($order->status === 'completed', 422, 'Pesanan belum selesai.');
        $order->update(['rating' => $data['rating']]);

        return response()->json(['order' => $this->orderData($order->fresh()->load(['customer', 'courier']))]);
    }

    private function authorizeAccess(Request $request): void
    {
        abort_unless($this->actor($request), 401);
    }

    private function requireRole(Request $request, string $role): User
    {
        $user = $this->requireUser($request);
        if ($user->role !== $role) {
            abort(403, 'Sesi aktif adalah akun '.($user->role === 'courier' ? 'kurir' : 'pelanggan').'. Silakan logout lalu login sebagai '.($role === 'courier' ? 'kurir' : 'pelanggan').'.');
        }
        return $user;
    }

    private function requireUser(Request $request): User
    {
        $this->authorizeAccess($request);
        $user = $this->actor($request);
        abort_unless($user instanceof User, 401);

        $user = $user->fresh();
        abort_unless($user, 401);

        return $user;
    }

    private function isAdmin(Request $request): bool
    {
        return $request->session()->get('yojek_admin') === true || $this->actorRole($request) === 'admin';
    }

    private function token(Request $request): ?string
    {
        return $request->header('X-Yojek-Token');
    }

    private function actor(Request $request): User|array|null
    {
        $token = $this->token($request);
        if ($token) {
            $session = YojekSession::query()->where('token_hash', hash('sha256', $token))->where('expires_at', '>', now())->first();
            if (! $session) return null;
            return $session->role === 'admin' ? ['role' => 'admin'] : $session->user;
        }
        $user = $request->user()?->fresh();
        if ($user) return $user;
        if ($request->session()->get('yojek_admin') === true) return ['role' => 'admin'];
        return null;
    }

    private function actorRole(Request $request): ?string
    {
        $actor = $this->actor($request);
        return is_array($actor) ? $actor['role'] : $actor?->role;
    }

    private function createToken(?int $userId, string $role): string
    {
        $token = Str::random(80);
        YojekSession::create([
            'token_hash' => hash('sha256', $token),
            'user_id' => $userId,
            'role' => $role,
            'expires_at' => now()->addDays(30),
        ]);
        return $token;
    }

    private function username(User $user): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($user->name, ' -')) ?: 'user-'.$user->id);
    }

    private function actorData(Request $request): array
    {
        if ($this->isAdmin($request)) {
            return [
                'role' => 'admin',
                'isAdmin' => true,
            ];
        }

        $user = $this->actor($request);
        if (is_array($user)) return ['role' => 'admin', 'isAdmin' => true];
        abort_unless($user, 401);

        return [
            'id' => $user->id,
            'role' => $user->role,
            'username' => $this->username($user),
            'name' => $user->name,
            'phone' => $user->phone,
            'address' => $user->address,
            'vehicle' => $user->vehicle,
            'isActive' => $user->is_active !== false,
            'adminDisabled' => $user->admin_disabled === true,
        ];
    }

    private function orderData(Order $order): array
    {
        return [
            'id' => $order->id,
            'date' => optional($order->created_at)->toDateString(),
            'createdAt' => optional($order->created_at)->toISOString(),
            'acceptedAt' => optional($order->accepted_at)->toISOString(),
            'arrivedAt' => optional($order->arrived_at)->toISOString(),
            'completedAt' => optional($order->completed_at)->toISOString(),
            'updatedAt' => optional($order->updated_at)->toISOString(),
            'customerId' => $order->customer_user_id,
            'customerUsername' => $order->customer_username,
            'customerName' => $order->customer_name,
            'customerPhone' => $order->customer_phone,
            'item' => $order->item,
            'category' => $order->category,
            'qty' => $order->qty,
            'pickup' => $order->pickup,
            'dropoff' => $order->dropoff,
            'note' => $order->note,
            'courierId' => $order->courier_user_id,
            'courier' => $order->courier?->name ?: 'Belum Diambil',
            'courierName' => $order->courier?->name,
            'status' => $order->status,
            'revenue' => (float) $order->revenue,
            'rating' => $order->rating ?: 0,
        ];
    }

    private function financeData(FinanceEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'orderId' => $entry->order_id,
            'date' => optional($entry->created_at)->toDateString(),
            'category' => $entry->category,
            'type' => $entry->type,
            'amount' => (float) $entry->amount,
            'note' => $entry->note,
        ];
    }

    private function courierData(User $courier): array
    {
        return [
            'id' => $courier->id,
            'username' => $this->username($courier),
            'name' => $courier->name,
            'phone' => $courier->phone,
            'vehicle' => $courier->vehicle,
            'isActive' => $courier->is_active !== false,
            'adminDisabled' => $courier->admin_disabled === true,
            'attendancePhoto' => $courier->attendance_photo ?: '',
            'attendanceAt' => optional($courier->attendance_at)->toISOString(),
            'attendanceLocation' => $courier->attendance_location,
        ];
    }
}
