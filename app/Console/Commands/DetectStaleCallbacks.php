<?php

namespace App\Console\Commands;

use App\Models\CallbackIdempotency;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DetectStaleCallbacks extends Command
{
    protected $signature = 'mpesa:detect-stale-callbacks';

    protected $description = 'Mark pending callback records as timed_out if they exceed the timeout threshold';

    public function handle(): int
    {
        $timeoutMinutes = config('mpesa.callback_timeout_minutes', 10);
        $cutoff = now()->subMinutes($timeoutMinutes);

        $staleRecords = CallbackIdempotency::where('status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->get();

        $count = 0;

        foreach ($staleRecords as $record) {
            $record->update(['status' => 'timed_out']);

            try {
                app(AuditService::class)->log(
                    action: 'callback.timed_out',
                    entity: 'callback',
                    entityId: $record->id,
                    user: null,
                    details: [
                        'correlation_id' => $record->correlation_id,
                        'checkout_request_id' => $record->checkout_request_id,
                        'user_id' => $record->user_id,
                        'amount' => $record->amount,
                    ],
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to log callback timeout audit entry', [
                    'callback_id' => $record->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $count++;
        }

        $this->info("Marked {$count} stale callback(s) as timed_out.");

        return Command::SUCCESS;
    }
}
