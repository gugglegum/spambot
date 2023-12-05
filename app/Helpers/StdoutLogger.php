<?php

declare(strict_types=1);
namespace App\Helpers;

class StdoutLogger
{
    public static function log(string $message): void
    {
        echo date("Y-m-d H:i:s") . " {$message}\n";
    }
}
