<?php

namespace App\Helpers;

use Illuminate\Support\Collection;
use Telegram\Bot\Objects\Message;

class TelegramHelper
{
    public static function convertChatId(int $chatId, string $chatType): int {
        $chatIdStr = (string) $chatId;

        if ($chatType === "supergroup" || $chatType === "channel") {
            if (str_starts_with($chatIdStr, "-100")) {
                $chatIdStr = substr($chatIdStr, 4);
            }
        }

        return (int) $chatIdStr;
    }

    /**
     * Returns full-name (first_name <space> last_name) of a message sender
     *
     * @param Message $message
     * @return string
     */
    public static function getMessageFrom(Message $message): string
    {
        if (!empty($message->sender_chat)) {
            return $message->sender_chat->title;
        } else {
            return $message->from->firstName . (!empty($message->from->lastName) ? ' ' . $message->from->lastName : '');
        }
    }

    /**
     * Returns a full-name and username if present of a message sender
     *
     * @param Message $message
     * @return string
     */
    public static function getMessageFromWithUsername(Message $message): string
    {
        return TelegramHelper::getMessageFrom($message) . (isset($message->from->username) ? " (@{$message->from->username})" : '');
    }

    /**
     * Returns ID string of a message sender ("user12345678" or "channel12345678")
     *
     * @param Message $message
     * @return string
     */
    public static function getMessageFromId(Message $message): string
    {
        if (!empty($message->senderChat)) {
            return 'channel' . self::convertChatId($message->senderChat->id, $message->senderChat->type);
        } else {
            return 'user' . $message->from?->id;
        }
    }
}
