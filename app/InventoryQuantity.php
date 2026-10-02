<?php

namespace App;

use Illuminate\Validation\ValidationException;

final class InventoryQuantity
{
    public const MAX = 999999999999999;

    public static function parse(string $value): int
    {
        if (! preg_match('/\A(-?)(\d{1,12})(?:\.(\d{1,3}))?\z/', $value, $parts)) {
            throw ValidationException::withMessages(['quantity' => 'Gunakan angka desimal dengan maksimal 12 digit dan 3 angka pecahan.']);
        }
        $units = ((int) $parts[2] * 1000) + (int) str_pad($parts[3] ?? '', 3, '0');

        return $parts[1] === '-' ? -$units : $units;
    }

    public static function format(int $units): string
    {
        return ($units < 0 ? '-' : '').intdiv(abs($units), 1000).'.'.str_pad((string) (abs($units) % 1000), 3, '0', STR_PAD_LEFT);
    }
}
