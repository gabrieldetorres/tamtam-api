<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $service) {}

    /**
     * GET /api/v1/reports/summary?from=2026-11-01&to=2026-11-16
     * No dates = today. Only "from" = from that day until today.
     */
    public function summary(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return response()->json(['data' => $this->service->summary($from, $to)]);
    }

    /**
     * GET /api/v1/reports/export?from=&to=  -> CSV file, one row per sold item.
     * The browser must call this with fetch() + the login token, then save the result.
     */
    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);

        $export = $this->service->export($from, $to); // validates the range before streaming starts

        return response()->streamDownload(function () use ($export) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 marker so Excel shows ₱ and accents correctly

            fputcsv($out, ReportService::CSV_HEADER, ',', '"', '');
            foreach ($export['rows'] as $row) {
                fputcsv($out, $row, ',', '"', '');
            }

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
}
