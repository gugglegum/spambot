<?php

namespace App\Helpers;

class StringHelper
{
    /**
     * Trims a string if it's longer than $maxLength characters and on trimming replace last N chars to $ending.
     * Result may be longer than $maxLength if $maxLength is less than length of $ending.
     *
     * @param string $str
     * @param int $maxLength
     * @param string $ending
     * @param string $encoding
     * @return string
     */
    public static function trimIfTooLong(string $str, int $maxLength, string $ending = '...', string $encoding = 'UTF-8'): string
    {
        if (mb_strlen($str, $encoding) > $maxLength) {
            $str = mb_substr($str, 0, max(0, $maxLength - mb_strlen(3, $encoding)), $encoding) . $ending;
        }
        return $str;
    }

    /**
     * Replaces all probably repeating white-spaces (and also UTF-8 non-breaking space "0xC2 0xA0") to normal single
     * spaces (0x32) and also trims string
     *
     * @param string $str
     * @return string
     */
    public static function filterSpaces(string $str): string
    {
        return preg_replace('/(?:\s|\xC2\xA0)+/', "\x20", trim($str));
    }

    /**
     * Parses a string that contains some identifiers (for example, order numbers, shipment UIDs, etc.) separated by
     * comma (",") or semicolon (";"). Extra spaces around separators are trimmed.
     *
     * @param string $str
     * @return string[]     Returns a list of identifiers
     */
    public static function parseIdentifiersListInString(string $str): array
    {
        return preg_split('/\s*[,;]\s*/', trim($str), -1, PREG_SPLIT_NO_EMPTY);
    }

    public static function escapeMarkdownV2(string $text): string
    {
        $escapeChars = str_split('_*[]()~`>#+-=|{}.!');
        $replacements = array_map(function(string $char) { return '\\' . $char; }, $escapeChars);
        return str_replace($escapeChars, $replacements, $text);
    }
}
