<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Services\SalesReportEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $service) {}

    
    public function summary(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return response()->json(['data' => $this->service->summary($from, $to)]);
    }

    
    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);

        $export = $this->service->export($from, $to); 

        return response()->streamDownload(function () use ($export) {
            $out = fopen('php://output', 'w');
            $this->service->writeCsv($out, $export['rows']);
            fclose($out);
        }, $export['filename'], ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function range(Request $request): array
    {
        $v = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return [$v['from'] ?? null, $v['to'] ?? null];
    }

    /**
     * Trigger the 30-day sales report check from a trusted external scheduler (e.g. cron-job.org).
     */
    public function runScheduled(Request $request, SalesReportEmailService $emailService): JsonResponse
    {
        $expected = (string) config('tamtam.sales_report_email.cron_secret', '');
        if ($expected === '') {
            return response()->json(['message' => 'Scheduled report secret is not configured.'], 503);
        }

        $provided = (string) ($request->header('X-Cron-Secret') ?? $request->bearerToken() ?? '');
        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $lock = Cache::lock('sales_report_email_send', 60);
        if (! $lock->get()) {
            return response()->json(['sent' => false, 'reason' => 'in_progress'], 202);
        }

        try {
            $result = $emailService->send(dueOnly: true);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } finally {
            $lock->release();
        }

        return response()->json([
            'sent' => $result['sent'],
            'reason' => $result['reason'] ?? null,
            'filename' => $result['filename'] ?? null,
            'csv_filename' => $result['csv_filename'] ?? null,
            'recipients' => $result['recipients'] ?? [],
        ]);
    }
}
