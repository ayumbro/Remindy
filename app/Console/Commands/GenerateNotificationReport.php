<?php

namespace App\Console\Commands;

use App\Services\NotificationTrackingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateNotificationReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:generate-report
                            {--format=json : Output format (json, csv)}
                            {--save : Save report to storage}
                            {--email= : Email address to send the report to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a comprehensive notification system health report';

    /**
     * Execute the console command.
     */
    public function handle(NotificationTrackingService $trackingService): int
    {
        $this->info('Generating notification system health report...');

        try {
            $report = $trackingService->generateHealthReport();
            $format = $this->option('format');

            // Display report summary
            $this->displayReportSummary($report);

            // Save report if requested
            if ($this->option('save')) {
                $this->saveReport($report, $format);
            }

            // Email report if requested
            if ($this->option('email')) {
                $this->warn('Email functionality not yet implemented. Report saved to storage instead.');
                $this->saveReport($report, $format);
            }

            // Output full report if not saving
            if (! $this->option('save') && ! $this->option('email')) {
                $this->outputReport($report, $format);
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to generate report: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Display a summary of the report.
     */
    private function displayReportSummary(array $report): void
    {
        $this->newLine();
        $this->info('=== Notification System Health Report ===');
        $this->info("Generated: {$report['timestamp']}");

        // Overall health
        $healthColor = match ($report['overall_health']) {
            'excellent' => 'green',
            'good' => 'green',
            'fair' => 'yellow',
            'poor' => 'red',
            'critical' => 'red',
            default => 'white',
        };

        $this->line("Overall Health: <fg={$healthColor}>".strtoupper($report['overall_health']).'</>');

        // Alerts
        if (! empty($report['alerts'])) {
            $this->newLine();
            $this->warn('Alerts:');
            foreach ($report['alerts'] as $alert) {
                $color = match ($alert['severity']) {
                    'critical' => 'red',
                    'warning' => 'yellow',
                    'info' => 'blue',
                    default => 'white',
                };
                $this->line("  <fg={$color}>[{$alert['severity']}]</> {$alert['message']}");
            }
        }

        // Key metrics
        $metrics = $report['metrics'];
        $this->newLine();
        $this->info('Key Metrics:');

        if (! empty($metrics['channel_performance'])) {
            foreach ($metrics['channel_performance'] as $channel) {
                $this->line("  {$channel['channel']}: {$channel['total']} sent, ".
                           round($channel['success_rate'], 1).'% success rate');
            }
        }

        $engagement = $metrics['user_engagement'];
        $this->line("  User Engagement: {$engagement['users_with_notifications']}/{$engagement['total_users']} users (".
                   round($engagement['engagement_rate'], 1).'%)');

        // Recommendations
        if (! empty($report['recommendations'])) {
            $this->newLine();
            $this->info('Recommendations:');
            foreach ($report['recommendations'] as $rec) {
                $this->line("  [{$rec['priority']}] {$rec['message']}");
            }
        }
    }

    /**
     * Save the report to storage.
     */
    private function saveReport(array $report, string $format): void
    {
        $timestamp = now()->format('Y-m-d-H-i-s');
        $filename = "notification-report-{$timestamp}.{$format}";

        $content = match ($format) {
            'csv' => $this->convertToCsv($report),
            default => json_encode($report, JSON_PRETTY_PRINT),
        };

        Storage::disk('local')->put("reports/{$filename}", $content);
        $this->info("Report saved to: storage/app/reports/{$filename}");
    }

    /**
     * Output the full report.
     */
    private function outputReport(array $report, string $format): void
    {
        $this->newLine();
        $this->info('=== Full Report ===');

        if ($format === 'csv') {
            $this->line($this->convertToCsv($report));
        } else {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));
        }
    }

    /**
     * Convert report to CSV format.
     */
    private function convertToCsv(array $report): string
    {
        $csv = "Notification System Health Report\n";
        $csv .= "Generated: {$report['timestamp']}\n";
        $csv .= "Overall Health: {$report['overall_health']}\n\n";

        // Channel Performance
        $csv .= "Channel Performance\n";
        $csv .= "Channel,Total,Sent,Delivered,Failed,Success Rate,Failure Rate\n";

        foreach ($report['metrics']['channel_performance'] as $channel) {
            $csv .= "{$channel['channel']},{$channel['total']},{$channel['sent']},{$channel['delivered']},{$channel['failed']},".
                   round($channel['success_rate'], 2).'%,'.round($channel['failure_rate'], 2)."%\n";
        }

        // User Engagement
        $engagement = $report['metrics']['user_engagement'];
        $csv .= "\nUser Engagement\n";
        $csv .= "Total Users,Users with Notifications,Engagement Rate,Avg Notifications per User\n";
        $csv .= "{$engagement['total_users']},{$engagement['users_with_notifications']},".
               round($engagement['engagement_rate'], 2)."%,{$engagement['avg_notifications_per_user']}\n";

        return $csv;
    }
}
