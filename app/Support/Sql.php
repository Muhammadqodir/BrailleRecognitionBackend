<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Raw SQL fragments that differ between MariaDB (production) and SQLite
 * (tests), built through the connection's own grammar.
 */
class Sql
{
    /** A JSON field as text, e.g. json('result_json', 'engine'). */
    public static function json(string $column, string $key): string
    {
        return DB::connection()->getQueryGrammar()->wrap("{$column}->{$key}");
    }

    /**
     * Where a translation was read: "device" when the phone recognised it,
     * "server" for everything else (the OCR host, and all legacy rows).
     */
    public static function engine(): string
    {
        return 'CASE WHEN '.self::json('result_json', 'engine')." = 'device' THEN 'device' ELSE 'server' END";
    }

    /** A translation that found no text. */
    public static function emptyResult(): string
    {
        return "(result IS NULL OR TRIM(result) = '')";
    }
}
