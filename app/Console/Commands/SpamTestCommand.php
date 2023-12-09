<?php

namespace App\Console\Commands;

use App\Helpers\SqliteDbHelper;
use App\Helpers\TelegramHelper;
use App\ResourceManager;
use App\Telegram\SpamDetector;
use App\Telegram\SpamDetectorBadWordsStat;
use Illuminate\Support\Collection;
use Telegram\Bot\Objects\Message;

class SpamTestCommand extends AbstractCommand
{
    private \Aura\Sql\ExtendedPdo $pdo;
    protected const TYPES = [
        'message',
        'edited_message',
        'channel_post',
        'edited_channel_post',
        'inline_query',
        'chosen_inline_result',
        'callback_query',
        'shipping_query',
        'pre_checkout_query',
        'poll',
        'poll_answer',
        'my_chat_member',
        'chat_member',
        'chat_join_request',
    ];

    /**
     * @param ResourceManager $resourceManager
     * @throws \Exception
     */
    public function __construct(ResourceManager $resourceManager)
    {
        parent::__construct($resourceManager);
        $this->pdo = $this->resourceManager->getSqliteDb();
    }

    public function __invoke(): ?int
    {
        $badWordsStat = new SpamDetectorBadWordsStat();
        //$skipUpdates = [801812076]; // skip updates with expected false-positives

        $didiDighomiChatId = 1677720183;
        $stmt = $this->pdo->prepare("SELECT * FROM `messages` WHERE `group_id` = :group_id ORDER BY id");
        $stmt->execute([
            'group_id' => $didiDighomiChatId,
        ]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $messageRow) {
//            if ($messageRow['id'] != 27667) {
//                continue;
//            }
            $message = $this->getMessage($messageRow);
            echo "\nMsgID: {$message->messageId}\nDate: " . date('Y-m-d H:i:s', $message->date) . "\nFrom: " . TelegramHelper::getMessageFromWithUsername($message). "\n";
            echo "---------------------\n" . $message->text . "\n---------------------\n";
//            if (in_array($update->update_id, $skipUpdates)) {
//                continue;
//            }

            $spamDetector = new SpamDetector($this->pdo, $message, $badWordsStat);
            $spamDetector->rate();
            echo "Total rate: " . $spamDetector->rate . "\n";
            echo "Messages from this user: " . $spamDetector->messagesCountFromUser . "\n";
            echo "Days since first message: " . ($spamDetector->daysSinceFirstMessage !== null ? round($spamDetector->daysSinceFirstMessage, 2) : 'N/A') . "\n";
            $isSpamDetected = $spamDetector->rate < 0;

            if ($isSpamDetected != (bool) $messageRow['is_spam']) {
                echo "ERROR: ";
                if ($isSpamDetected) {
                    echo "spam detected in MsgID: {$message->messageId}, but shouldn't be spam\n";
                } else {
                    echo "spam not detected in MsgID: {$message->messageId}, but should be spam\n";
                }
            }
        }
        $stmt->closeCursor();

        if (isset($badWordsStat->stat)) {
            arsort($badWordsStat->stat);
            print_r($badWordsStat->stat);
        }

        return 0;
    }

    private function getMessage(array $row): Message
    {
        return new Message([
            'message_id' => (int) $row['id'],
            'from' => [
                'id' => TelegramHelper::userIdToInt($row['from_id']) ?? 1087968824, // by default @GroupAnonymousBot
                'first_name' => $row['from'],
                'username' => $row['username'],
            ],
            'chat' => [
                'id' => (int) ('-100' . $row['group_id']),
                'type' => 'supergroup',
            ],
            'date' => (int) $row['date_unixtime'],
            'text' => $row['text'],
        ]);
    }

//    /**
//     * Detect type based on properties.
//     */
//    public function detectType(Collection $update): ?string
//    {
//        return $update->keys()
//            ->intersect(static::TYPES)
//            ->pop();
//    }

//    public function getMessage(Collection $update): ?Message
//    {
//        return match ($this->detectType($update)) {
//            'message' => $update->message,
//            'edited_message' => $update->editedMessage,
//            'channel_post' => $update->channelPost,
//            'edited_channel_post' => $update->editedChannelPost,
//            'inline_query' => $update->inlineQuery,
//            'chosen_inline_result' => $update->chosenInlineResult,
//            'callback_query' => $update->callbackQuery->has('message') ? $update->callbackQuery->message : collect(),
//            'shipping_query' => $update->shippingQuery,
//            'pre_checkout_query' => $update->preCheckoutQuery,
//            'poll' => $update->poll,
//            default => null,
//        };
//    }

}
