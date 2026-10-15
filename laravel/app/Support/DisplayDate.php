<?php

namespace App\Support;

/**
 * Short dates for dashboard lists, same output as format_dashboard_date() in dashboard.php.
 */
class DisplayDate
{
    public static function short(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '—';
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return $value;
        }

        return date('M j, Y', $ts);
    }
}
