<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Report - {{ $store->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif; font-size: 11px; color: #1f2937; line-height: 1.4; padding: 24px; }
        h1 { font-size: 18px; margin-bottom: 4px; color: #111827; }
        .subtitle { color: #6b7280; font-size: 10px; margin-bottom: 20px; }
        .section { margin-bottom: 24px; }
        .section-title { font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 10px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
        .stats-row { display: table; width: 100%; margin-bottom: 8px; }
        .stat { display: table-cell; width: 33%; padding-right: 16px; }
        .stat-label { font-size: 9px; text-transform: uppercase; color: #6b7280; margin-bottom: 2px; }
        .stat-value { font-size: 16px; font-weight: 600; color: #111827; }
        .chart-bar-container { margin-bottom: 6px; }
        .chart-bar-label { font-size: 9px; color: #4b5563; margin-bottom: 2px; }
        .chart-bar-bg { background: #e5e7eb; border-radius: 2px; height: 16px; overflow: hidden; }
        .chart-bar-fill { background: #4f46e5; height: 100%; border-radius: 2px; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        th, td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; color: #374151; font-size: 9px; text-transform: uppercase; }
        .text-right { text-align: right; }
        .footer { margin-top: 32px; font-size: 9px; color: #9ca3af; }
        .chart-vertical { margin-top: 8px; }
        .chart-vertical-table { width: 100%; table-layout: fixed; border-collapse: collapse; }
        .chart-vertical td { vertical-align: bottom; text-align: center; padding: 0 1px; width: 1%; }
        .chart-bar-col { height: 50px; background: #e5e7eb; position: relative; }
        .chart-bar-val { position: absolute; bottom: 0; left: 10%; right: 10%; background: #4f46e5; min-height: 2px; }
        .chart-bar-date { font-size: 7px; color: #6b7280; margin-top: 2px; overflow: hidden; }
    </style>
</head>
<body>
    <h1>{{ $store->name }}</h1>
    <p class="subtitle">Sales Report · {{ $dateFrom->format('M j, Y') }} – {{ $dateTo->format('M j, Y') }}</p>

    <div class="section">
        <div class="section-title">Summary</div>
        <div class="stats-row">
            <div class="stat">
                <div class="stat-label">Paid Invoices</div>
                <div class="stat-value">{{ number_format($totalCount) }}</div>
            </div>
            <div class="stat">
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value">{{ number_format($totalAmount, 2) }} {{ $currency }}</div>
            </div>
            <div class="stat">
                <div class="stat-label">Avg per Invoice</div>
                <div class="stat-value">{{ $totalCount > 0 ? number_format($totalAmount / $totalCount, 2) : '0' }} {{ $currency }}</div>
            </div>
        </div>
    </div>

    @if(count($salesByDay) > 0)
    <div class="section">
        <div class="section-title">Sales by Day</div>
        @foreach($salesByDay as $day)
        <div class="chart-bar-container">
            <div class="chart-bar-label">{{ $day['date'] }} · {{ $day['count'] }} invoices</div>
            <div class="chart-bar-bg">
                <div class="chart-bar-fill" style="width: {{ min(100, ($day['count'] / $maxCount) * 100) }}%;"></div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    @if(count($topItems) > 0)
    <div class="section">
        <div class="section-title">Top Items</div>
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="text-right">Count</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topItems as $item)
                <tr>
                    <td>{{ $item['name'] }}</td>
                    <td class="text-right">{{ $item['count'] }}</td>
                    <td class="text-right">{{ number_format($item['total'], 2) }} {{ $item['currency'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if(count($salesByDay) > 0)
    <div class="section">
        <div class="section-title">Sales Chart</div>
        <div class="chart-vertical">
            <table class="chart-vertical-table"><tr>
            @foreach($salesByDay as $day)
            <td>
                <div class="chart-bar-col">
                    <div class="chart-bar-val" style="height: {{ $maxCount > 0 ? max(2, ($day['count'] / $maxCount) * 100) : 0 }}%;"></div>
                </div>
                <div class="chart-bar-date">{{ $day['date'] }}</div>
            </td>
            @endforeach
            </tr></table>
        </div>
    </div>
    @endif

    <p class="footer">Generated by D21 Panel · {{ now()->format('Y-m-d H:i') }} UTC</p>
</body>
</html>
