<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use PDO;

class RegistersSqliteMathFunctions
{
    /**
     * Register trigonometric helpers so Haversine SQL works on SQLite.
     */
    public static function register(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        $pdo = DB::connection()->getPdo();

        if (! $pdo instanceof PDO) {
            return;
        }

        $pdo->sqliteCreateFunction('acos', fn (float $value): float => acos($value), 1);
        $pdo->sqliteCreateFunction('cos', fn (float $value): float => cos($value), 1);
        $pdo->sqliteCreateFunction('sin', fn (float $value): float => sin($value), 1);
        $pdo->sqliteCreateFunction('radians', fn (float $degrees): float => deg2rad($degrees), 1);
    }
}
