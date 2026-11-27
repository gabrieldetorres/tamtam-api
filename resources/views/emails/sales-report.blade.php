<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $appName }} Sales Report</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; background-color: #f9fafb; margin: 0; padding: 24px;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
        <div style="background-color: #1c293d; color: #ffffff; padding: 20px 24px;">
            <h1 style="margin: 0; font-size: 20px;">{{ $appName }} &mdash; 30-Day Sales Report</h1>
            <p style="margin: 6px 0 0; font-size: 13px; color: #d1d5db;">
                Period: {{ $summary['from'] }} to {{ $summary['to'] }} ({{ $summary['timezone'] }})
            </p>
        </div>

        <div style="padding: 24px;">
            <p style="margin: 0 0 16px; font-size: 14px; line-height: 1.5;">
                Please find the automated 30-day sales report attached as <strong>{{ $pdfFilename }}</strong>@if (!empty($csvFilename)) and the CSV export as <strong>{{ $csvFilename }}</strong>@endif.
            </p>

            <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse: collapse; margin-bottom: 20px;">
                <tr>
                    <td style="border: 1px solid #e5e7eb; background: #f8fafc;">
                        <div style="font-size: 11px; color: #6b7280;">Total Sales</div>
                        <div style="font-size: 16px; font-weight: bold;">{{ $currency }} {{ number_format((float) $summary['sales'], 2) }}</div>
                    </td>
                    <td style="border: 1px solid #e5e7eb; background: #f8fafc;">
                        <div style="font-size: 11px; color: #6b7280;">Paid Orders</div>
                        <div style="font-size: 16px; font-weight: bold;">{{ number_format((int) $summary['orders']) }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="border: 1px solid #e5e7eb; background: #f8fafc;">
                        <div style="font-size: 11px; color: #6b7280;">Average Order</div>
                        <div style="font-size: 16px; font-weight: bold;">{{ $currency }} {{ number_format((float) $summary['avg_order'], 2) }}</div>
                    </td>
                    <td style="border: 1px solid #e5e7eb; background: #f8fafc;">
                        <div style="font-size: 11px; color: #6b7280;">Voided Orders</div>
                        <div style="font-size: 16px; font-weight: bold;">{{ number_format((int) $summary['voided']) }}</div>
                    </td>
                </tr>
            </table>

            @if (!empty($summary['top_sellers']))
                <h2 style="font-size: 14px; margin: 0 0 8px;">Top Selling Items</h2>
                <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="background-color: #f3f4f6; text-align: left;">
                            <th style="border-bottom: 1px solid #e5e7eb;">Item</th>
                            <th style="border-bottom: 1px solid #e5e7eb; text-align: right;">Qty</th>
                            <th style="border-bottom: 1px solid #e5e7eb; text-align: right;">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_slice($summary['top_sellers'], 0, 5) as $seller)
                            <tr>
                                <td style="border-bottom: 1px solid #f3f4f6;">{{ $seller['label'] ?? $seller['name'] }}</td>
                                <td style="border-bottom: 1px solid #f3f4f6; text-align: right;">{{ number_format((int) $seller['qty']) }}</td>
                                <td style="border-bottom: 1px solid #f3f4f6; text-align: right;">{{ $currency }} {{ number_format((float) $seller['revenue'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div style="padding: 14px 24px; background-color: #f9fafb; border-top: 1px solid #e5e7eb; font-size: 12px; color: #6b7280;">
            Sent automatically by {{ $appName }}.
        </div>
    </div>
</body>
</html>
