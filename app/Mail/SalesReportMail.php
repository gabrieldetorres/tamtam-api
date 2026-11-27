<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SalesReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{
     *     from: string,
     *     to: string,
     *     timezone: string,
     *     sales: string,
     *     orders: int,
     *     avg_order: string,
     *     voided: int,
     *     top_sellers: array<int, array<string, mixed>>,
     *     daily: array<int, array<string, mixed>>
     * }  $summary
     */
    public function __construct(
        public array $summary,
        public string $pdfBinary,
        public string $pdfFilename,
        public string $currency = 'PHP',
        public ?string $csvBinary = null,
        public ?string $csvFilename = null,
    ) {}

    public function envelope(): Envelope
    {
        $appName = (string) config('app.name', 'TamTam POS');

        return new Envelope(
            subject: sprintf('%s 30-Day Sales Report (%s to %s)', $appName, $this->summary['from'], $this->summary['to']),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.sales-report',
            with: [
                'appName' => (string) config('app.name', 'TamTam POS'),
                'summary' => $this->summary,
                'currency' => $this->currency,
                'pdfFilename' => $this->pdfFilename,
                'csvFilename' => $this->csvFilename,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $attachments = [
            Attachment::fromData(fn () => $this->pdfBinary, $this->pdfFilename)
                ->withMime('application/pdf'),
        ];

        if ($this->csvBinary !== null && $this->csvFilename !== null) {
            $attachments[] = Attachment::fromData(fn () => $this->csvBinary, $this->csvFilename)
                ->withMime('text/csv');
        }

        return $attachments;
    }
}
