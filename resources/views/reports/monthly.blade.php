
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>TAMTAM Monthly Sales Report</title>

    <style>
        @page {
            margin: 30px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #222;
        }

        .header {
            border-bottom: 3px solid #e84b5f;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #e84b5f;
        }

        .header p {
            margin: 5px 0 0;
            color: #666;
        }

        h2 {
            font-size: 14px;
            margin-top: 20px;
            margin-bottom: 10px;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-table td {
            width: 25%;
            border: 1px solid #ddd;
            padding: 12px;
        }

        .metric-label {
            color: #666;
            font-size: 9px;
        }

        .metric-value {
            font-size: 16px;
            font-weight: bold;
            margin-top: 5px;
        }

        table.records {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .records th {
            background: #e84b5f;
            color: white;
            padding: 7px 5px;
            text-align: left;
        }

        .records td {
            border-bottom: 1px solid #ddd;
            padding: 6px 5px;
        }

        .records tr:nth-child(even) {
            background: #f7f7f7;
        }

        .text-right {
            text-align: right;
        }

        .footer {
            margin-top: 25px;
            padding-top: 8px;
            border-top: 1px solid #ddd;
            color: #777;
            font-size: 8px;
        }
    </style>
</head>

<body>

    <div class="header">
        <h1>TAMTAM</h1>
        <p>Monthly Sales Report</p>
        <p>
            Reporting Period:
            {{ $summary['from'] }} to {{ $summary['to'] }}
        </p>
    </div>

    <h2>Sales Summary</h2>

    <table class="summary-table">
        <tr>
            <td>
                <div class="metric-label">Total Sales</div>
                <div class="metric-value">
                    ₱{{ $summary['sales'] }}
                </div>
            </td>

            <td>
                <div class="metric-label">Total Orders</div>
                <div class="metric-value">
                    {{ $summary['orders'] }}
                </div>
            </td>

            <td>
                <div class="metric-label">Average Order Value</div>
                <div class="metric-value">
                    ₱{{ $summary['avg_order'] }}
                </div>
            </td>

            <td>
                <div class="metric-label">Voided Orders</div>
                <div class="metric-value">
                    {{ $summary['voided'] }}
                </div>
            </td>
        </tr>
    </table>

    <h2>Top Sellers</h2>

    <table class="records">
        <thead>
            <tr>
                <th>Product</th>
                <th class="text-right">Quantity Sold</th>
                <th class="text-right">Revenue</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($summary['top_sellers'] as $seller)
                <tr>
                    <td>{{ $seller['label'] }}</td>
                    <td class="text-right">{{ $seller['qty'] }}</td>
                    <td class="text-right">₱{{ $seller['revenue'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">No sales records for this month.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Daily Sales</h2>

    <table class="records">
        <thead>
            <tr>
                <th>Date</th>
                <th class="text-right">Orders</th>
                <th class="text-right">Sales</th>
            </tr>
        </thead>

        <tbody>
            @foreach ($summary['daily'] as $day)
                <tr>
                    <td>{{ $day['date'] }}</td>
                    <td class="text-right">{{ $day['orders'] }}</td>
                    <td class="text-right">₱{{ $day['sales'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Detailed Sales Records</h2>

    <table class="records">
        <thead>
            <tr>
                <th>Date</th>
                <th>Order No.</th>
                <th>Item</th>
                <th>Size</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Price</th>
                <th class="text-right">Line Total</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($records as $record)
                <tr>
                    <td>{{ $record[0] }}</td>
                    <td>{{ $record[1] }}</td>
                    <td>{{ $record[2] }}</td>
                    <td>{{ $record[3] }}</td>
                    <td class="text-right">{{ $record[4] }}</td>
                    <td class="text-right">₱{{ $record[5] }}</td>
                    <td class="text-right">₱{{ $record[6] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">No detailed sales records for this month.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Generated automatically by TAMTAM POS.
        All amounts are in Philippine Peso (PHP).
    </div>

</body>
</html>
