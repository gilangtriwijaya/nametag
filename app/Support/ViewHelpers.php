<?php

namespace App\Support;

use Carbon\Carbon;

class ViewHelpers
{
    /**
     * Render a date/time in Asia/Jakarta timezone.
     * Accepts Carbon, DateTime or string; returns '—' for null/empty.
     *
     * @param mixed $value
     * @param string|null $format
     * @return string
     */
    public function datetime($value, ?string $format = null): string
    {
        $format = $format ?: 'd M Y H:i';

        if (is_null($value) || $value === '') {
            return '—';
        }

        try {
            $dt = $value instanceof Carbon ? $value : Carbon::parse($value);
            return $dt->timezone('Asia/Jakarta')->format($format);
        } catch (\Throwable $e) {
            return '—';
        }
    }
}
