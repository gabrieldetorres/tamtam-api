<?php

namespace App\Services;

use App\Mail\SalesReportMail;
use App\Repositories\AuditLogRepository;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class SalesReportEmailService
{
    public const AUDIT_KIND = 'sales_report_emailed';

    public function __construct(
        private ReportService $reports,
        private SalesReportPdfGenerator $pdf,
        private AuditLogRepository $audit,
    ) {}

    /** @return array{0: string, 1: string} */
    public function defaultWindow(int $days = 30): array
    {
        $days = max(1, min(366, $days));
        $tz = (string) config('tamtam.report_timezone', 'Asia/Manila');

        // Cover the last $days completed calendar days in the stall's timezone.
        $toDay = now($tz)->startOfDay()->toImmutable()->subDay();
        $fromDay = $toDay->subDays($days - 1);

        return [$fromDay->toDateString(), $toDay->toDateString()];
    }

    public function isDue(int $intervalDays = 30): bool
{
    $intervalMinutes = (int) config('tamtam.sales_report_email.interval_minutes', 0);

    $lastSent = $this->audit->lastTimestampForKind(self::AUDIT_KIND);

    if ($lastSent === null) {
        return true;
    }

    $tz = (string) config('tamtam.report_timezone', 'Asia/Manila');
    $lastSent = $lastSent->setTimezone($tz);

    // Use minutes for temporary testing when configured.
    if ($intervalMinutes > 0) {
        return $lastSent->diffInMinutes(now($tz)) >= $intervalMinutes;
    }

    // Otherwise, use the normal day-based interval.
    $intervalDays = max(1, $intervalDays);
    $lastDay = $lastSent->startOfDay();
    $today = now($tz)->startOfDay()->toImmutable();

    return $lastDay->diffInDays($today) >= $intervalDays;
}


    /**
     * Generate and email the 30-day PDF sales report.
     *
     * @param  array<int, string>  $recipients
     * @return array{sent: bool, reason?: string, filename?: string, recipients?: array<int, string>, summary?: array<string, mixed>}
     */
    public function send(
        ?string $from = null,
        ?string $to = null,
        array $recipients = [],
        bool $dueOnly = false,
        bool $force = false,
        ?int $days = null,
    ): array {
        $config = config('tamtam.sales_report_email', []);
        $enabled = (bool) ($config['enabled'] ?? true);
        $intervalDays = max(1, (int) ($config['interval_days'] ?? 30));

        if (! $force && ! $enabled) {
            return ['sent' => false, 'reason' => 'disabled'];
        }

        if (! $force && $dueOnly && ! $this->isDue($intervalDays)) {
            return ['sent' => false, 'reason' => 'not_due'];
        }

        $targets = $this->resolveRecipients($recipients, $config['recipients'] ?? []);

        if ($from === null && $to === null) {
            [$from, $to] = $this->defaultWindow($days ?? $intervalDays);
        }

        $summary = $this->reports->summary($from, $to);
        $export = $this->reports->export($from, $to);
        $csvRows = $export['rows']->all();
        $csvBinary = $this->reports->toCsvString($csvRows);
        $csvFilename = $export['filename'];
        $currency = (string) ($config['currency'] ?? 'PHP');
        $pdfBinary = $this->pdf->generate($summary, $currency, null, $csvRows);
        $filename = sprintf('tamtam-sales-%s-to-%s.pdf', $summary['from'], $summary['to']);

        Mail::to($targets)->send(new SalesReportMail(
            $summary,
            $pdfBinary,
            $filename,
            $currency,
            $csvBinary,
            $csvFilename,
        ));

        $this->audit->record(
            self::AUDIT_KIND,
            sprintf('Sent %s to %s', $filename, implode(', ', $targets)),
            null
        );

        return [
            'sent' => true,
            'filename' => $filename,
            'csv_filename' => $csvFilename,
            'recipients' => $targets,
            'summary' => $summary,
        ];
    }

    /**
     * @param  array<int, string>  $override
     * @param  array<int, string>  $configured
     * @return array<int, string>
     */
    private function resolveRecipients(array $override, array $configured): array
    {
        $raw = $override !== [] ? $override : $configured;
        $targets = array_values(array_unique(array_filter(array_map('trim', $raw))));

        if ($targets === []) {
            throw new InvalidArgumentException('No recipient email configured. Set SALES_REPORT_RECIPIENT_EMAILS or pass --recipient.');
        }

        foreach ($targets as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException("Invalid recipient email address: {$email}");
            }
        }

        return $targets;
    }
}
