<?php

namespace App\Console\Commands;

use App\Models\FinanceEntry;
use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetYojekOperationalData extends Command
{
    protected $signature = 'yojek:reset-operational-data {--force : Run without an interactive confirmation}';

    protected $description = 'Delete all Yojek orders, finance records, customer accounts, and courier accounts while preserving administrators';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Delete all customer, courier, order, and finance data?')) {
            $this->info('Reset cancelled.');

            return self::SUCCESS;
        }

        [$finance, $orders, $users] = DB::transaction(function (): array {
            $finance = FinanceEntry::query()->delete();
            $orders = Order::query()->delete();
            $users = User::query()->whereIn('role', ['customer', 'courier'])->delete();

            return [$finance, $orders, $users];
        });

        $this->info("Reset complete: {$users} customer/courier accounts, {$orders} orders, and {$finance} finance entries deleted. Admin accounts were preserved.");

        return self::SUCCESS;
    }
}
