<?php

return [

    // "Today", "this week" and the day totals in reports follow the stall's clock,
    // not the server's. Orders themselves are always stored in UTC.
    'report_timezone' => env('REPORT_TIMEZONE', 'Asia/Manila'),

    'sales_report_email' => [
        'enabled' => filter_var(env('SALES_REPORT_EMAIL_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'recipients' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) (
                env('SALES_REPORT_RECIPIENT_EMAILS')
                ?: env('ADMIN_EMAILS')
                ?: env('MAIL_FROM_ADDRESS', '')
            ))
        ))),
        'interval_days' => (int) env('SALES_REPORT_INTERVAL_DAYS', 30),
        'interval_minutes' => (int) env('SALES_REPORT_INTERVAL_MINUTES', 0), //For testing
        'send_time' => env('SALES_REPORT_SEND_TIME', '08:00'),
        'currency' => env('SALES_REPORT_CURRENCY', 'PHP'),
        'cron_secret' => env('SALES_REPORT_CRON_SECRET'),
    ],

];
