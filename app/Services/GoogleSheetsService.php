<?php

namespace App\Services;

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\ClearValuesRequest;
use Google\Service\Sheets\ValueRange;
use RuntimeException;


class GoogleSheetsService
{
    private Sheets $sheets;

    public function __construct()
    {
        $credentialsPath = config('services.google_sheets.credentials_path');

        if (!$credentialsPath || !file_exists($credentialsPath)) {
            throw new RuntimeException(
                'Google Sheets credentials file was not found.'
            );
        }

        $client = new Client();

        $client->setAuthConfig($credentialsPath);
        $client->setScopes([
            Sheets::SPREADSHEETS,
        ]);

        $client->setApplicationName('TamTam POS');

        $this->sheets = new Sheets($client);
    }

    public function export(string $from,string $to,array $values): 
    array {
        
    $spreadsheetId = config(
        'services.google_sheets.spreadsheet_id'
    );

    $baseName = config(
        'services.google_sheets.sheet_name',
        'Sales Reports'
    );

    if (!$spreadsheetId) {
        throw new RuntimeException(
            'Google Spreadsheet ID is not configured.'
        );
    }

    $sheetTitle = "{$baseName} {$from} to {$to}";

        

        $spreadsheet = $this->sheets->spreadsheets->get(
            $spreadsheetId
        );

        $exists = false;

        foreach ($spreadsheet->getSheets() as $sheet) {
            if ($sheet->getProperties()->getTitle() === $sheetTitle) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $request = new BatchUpdateSpreadsheetRequest([
                'requests' => [
                    [
                        'addSheet' => [
                            'properties' => [
                                'title' => $sheetTitle,
                            ],
                        ],
                    ],
                ],
            ]);

            $this->sheets->spreadsheets->batchUpdate(
                $spreadsheetId,
                $request
            );
        }

        $range = "'{$sheetTitle}'!A:Z";

        $this->sheets->spreadsheets_values->clear(
            $spreadsheetId,
            $range,
            new ClearValuesRequest()
        );

        $this->sheets->spreadsheets_values->update(
            $spreadsheetId,
            "'{$sheetTitle}'!A1",
            new ValueRange([
                'values' => $values,
            ]),
            [
                'valueInputOption' => 'RAW',
            ]
        );

        return [
            'sheet_name' => $sheetTitle,
            'spreadsheet_url' =>
                "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/edit",
        ];
    }
}
