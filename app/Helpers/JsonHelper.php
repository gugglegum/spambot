<?php

namespace App\Helpers;

class JsonHelper
{
    public static function json_decode(string $json, ?bool $associative = null, int $depth = 512, int $flags = 0): mixed
    {
        $result = json_decode($json, $associative, $depth, $flags);
        if ($result === null) {
            throw new \Exception("Failed to decode JSON");
        }
        return $result;
    }
}
