<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ResetMonthlyUsage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $currentMonth = now()->format('Y-m');

        $count = Tenant::where('usage_reset_month', '!=', $currentMonth)
            ->orWhereNull('usage_reset_month')
            ->update([
                'documents_this_month' => 0,
                'ai_messages_this_month' => 0,
                'usage_reset_month' => $currentMonth,
            ]);

        Log::info("Uso mensual reiniciado para {$count} tenants");
    }
}
