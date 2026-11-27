<?php

namespace App\Console\Commands;

use App\Services\SalesReportEmailService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class SendSalesReportEmail extends Command
{
    protected $signature = 'reports:send-sales-email
        {--days= : Number of completed days to include (defaults to config)}
        {--from= : Start date in Y-m-d format}
        {--to= : End date in Y-m-d format}
        {--recipient=* : Override recipient email address(es)}
        {--due-only : Send only when at least the configured interval days have passed}
        {--force : Send even if disabled or not yet due}';

    protected $description = 'Generate the 30-day PDF sales summary report and email it via Gmail/SMTP';

    public function handle(SalesReportEmailService $service): int
    {
        $daysOption = $this->option('days');
        $days = $daysOption !== null ? (int) $daysOption : null;
        $from = $this->option('from');
        $to = $this->option('to');

        if (($from !== null) xor ($to !== null)) {
            $this->error('Both --from and --to must be provided together.');

            return self::FAILURE;
        }

        /** @var array<int, string> $recipients */
        $recipients = (array) $this->option('recipient');

        try {
            $result = $service->send(
                from: $from !== null ? (string) $from : null,
                to: $to !== null ? (string) $to : null,
                recipients: $recipients,
                dueOnly: (bool) $this->option('due-only'),
                force: (bool) $this->option('force'),
                days: $days,
            );
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $result['sent']) {
            $reason = $result['reason'] ?? 'skipped';
            $this->info("Skipped sending sales report ({$reason}).");

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Sent %s to %s.',
            $result['filename'],
            implode(', ', $result['recipients'])
        ));

        return self::SUCCESS;
    }
}
