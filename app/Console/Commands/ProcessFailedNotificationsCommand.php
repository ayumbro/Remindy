<?php

namespace App\Console\Commands;

use App\Jobs\ProcessFailedNotifications;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessFailedNotificationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:process-failed
                            {--dry-run : Show what would be processed without actually processing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process failed notifications that are ready for retry';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        try {
            if (! $dryRun) {
                ProcessFailedNotifications::dispatch();
                $this->line('[' . now()->format('H:i') . '] Failed notifications job dispatched');
            }
            // Silent for dry run to reduce noise

            Log::info('Process failed notifications command completed', [
                'dry_run' => $dryRun,
            ]);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error processing failed notifications: {$e->getMessage()}");

            Log::error('Error in process failed notifications command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
