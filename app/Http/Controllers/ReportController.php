<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Services\GoogleSheetsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            fwrite($out, "\xEF\xBB\xBF"); 

            fputcsv($out, ReportService::CSV_HEADER, ',', '"', '');
            foreach ($export['rows'] as $row) {
                fputcsv($out, $row, ',', '"', '');
            }

            fclose($out);
        }, $export['filename'], ['Content-Type' => 'text/csv; charset=UTF-8']);
    }


    public function exportToGoogleSheets(Request $request, GoogleSheetsService $googleSheets): JsonResponse
    { 
        [$from, $to] = $this->range($request);

        if (!$from || !$to) {
            return response()->json([
                'message' => 'Both start and end dates are required.'
            ], 422);
        }

        $export = $this->service->export($from, $to);

        $values = [ReportService::CSV_HEADER];

        foreach ($export['rows'] as $row) {
            $values[] = $row;
        }

        $result = $googleSheets->export($from, $to, $values);

        return response()->json([
            'data' => [
                'message' => 'Sales report exported successfully.',
                'sheet_name' => $result['sheet_name'],
                'spreadsheet_url' => $result['spreadsheet_url'],
            ],
        ]);
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
