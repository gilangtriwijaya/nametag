<?php

namespace App\Support;

class ChangeDiff
{
    public static function diff(array $old, array $new, array $except = []): array
    {
        $mask = array_flip($except);
        $out  = [];

        foreach ($new as $k => $v) {
            if (array_key_exists($k, $mask)) continue;

            $ov = $old[$k] ?? null;
            if ($v instanceof \DateTimeInterface) $v = $v->format('c');
            if ($ov instanceof \DateTimeInterface) $ov = $ov->format('c');

            if ($ov !== $v) {
                $out[$k] = ['from' => $ov, 'to' => $v];
            }
        }
        return $out;
    }
}
