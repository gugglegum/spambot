<?php

namespace App\Helpers;

use Illuminate\Support\Collection;

class TelegramHelper
{
    public static function convertChatId(int $chatId, string $chatType): int {
        // Преобразуем ID в строку для обработки
        $chatIdStr = (string) $chatId;

        // Для супергрупп и каналов удаляем префикс '-100'
        if ($chatType === "supergroup" || $chatType === "channel") {
            if (str_starts_with($chatIdStr, "-100")) {
                $chatIdStr = substr($chatIdStr, 4);
            }
        }

        // Преобразуем обратно в int и возвращаем
        return (int) $chatIdStr;
    }

    public static function getMessageFrom(Collection $message): string
    {
        if (!empty($message->sender_chat)) {
            return $message->sender_chat->title;
        } else {
            return $message->from->first_name . (!empty($message->from->last_name) ? ' ' . $message->from->last_name : '');
        }
    }

    public static function getMessageFromId(Collection $message): string
    {
        if (!empty($message->sender_chat)) {
            return 'channel' . self::convertChatId($message->sender_chat->id, $message->sender_chat->type);
        } else {
            return 'user' . $message->from?->id;
        }
    }
}
