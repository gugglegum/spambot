<?php

namespace App\Console\Commands;

use App\Helpers\SqliteDbHelper;
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
//        $updateId = 801812061;
        $updateId = 801812089;

        $badWordsStat = new SpamDetectorBadWordsStat();
        $skipUpdates = [801812076]; // skip updates with expected false-positives

        $files = glob('chat-history/*.json');
        foreach ($files as $updateFile) {
            echo "\n{$updateFile}\n";

            $shouldBeSpam = str_contains($updateFile, 'spam');

//            $update = new Message(json_decode(file_get_contents(self::getUpdateFile($updateId)), true, 512, JSON_THROW_ON_ERROR));
            $update = new Message(json_decode(file_get_contents($updateFile), true, 512, JSON_THROW_ON_ERROR));

            $message = $this->getMessage($update);
            if (!isset($message->text)) {
                continue;
            }
            echo $message->text . "\n";
//            var_dump($message->toArray());
            if (in_array($update->update_id, $skipUpdates)) {
                continue;
            }

            $spamDetector = new SpamDetector($this->pdo, $message, $badWordsStat);
            $spamDetector->rate();
//            echo "Messages count from this user: " . $spamDetector->messagesCountFromUser . "\n";
//            echo "Days from first message: " . ($spamDetector->dateOfFirstUserMessage ? round((time() - $spamDetector->dateOfFirstUserMessage) / 3600 / 24) : 'N/A') . "\n";
            echo "Total rate: " . $spamDetector->rate . "\n";
            $isSpamDetected = $spamDetector->rate < 0;

            if ($isSpamDetected != $shouldBeSpam) {
                echo "ERROR: ";
                if ($isSpamDetected) {
                    echo "spam detected in {$updateFile}, but shouldn't be spam\n";
                } else {
                    echo "spam not detected in {$updateFile}, but should be spam\n";
                }
            }
        }

//        echo json_encode($badWordsStat->stat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR);
        if (isset($badWordsStat->stat)) {
            arsort($badWordsStat->stat);
            print_r($badWordsStat->stat);
        }

        return 0;
    }

    /**
     * Detect type based on properties.
     */
    public function detectType(Collection $update): ?string
    {
        return $update->keys()
            ->intersect(static::TYPES)
            ->pop();
    }

    public function getMessage(Collection $update): ?Message
    {
        return match ($this->detectType($update)) {
            'message' => $update->message,
            'edited_message' => $update->editedMessage,
            'channel_post' => $update->channelPost,
            'edited_channel_post' => $update->editedChannelPost,
            'inline_query' => $update->inlineQuery,
            'chosen_inline_result' => $update->chosenInlineResult,
            'callback_query' => $update->callbackQuery->has('message') ? $update->callbackQuery->message : collect(),
            'shipping_query' => $update->shippingQuery,
            'pre_checkout_query' => $update->preCheckoutQuery,
            'poll' => $update->poll,
            default => null,
        };
    }

    private function getUpdateFile(int $updateId): string
    {
        if (file_exists('chat-history/' . $updateId . '.json')) {
            return 'chat-history/' . $updateId . '.json';
        } elseif (file_exists('chat-history/' . $updateId . '.spam.json')) {
            return 'chat-history/' . $updateId . '.spam.json';
        } else {
            throw new \Exception("Missing update file for update_id = {$updateId}");
        }
    }

}
