<?php

declare(strict_types=1);
namespace App\Console\Commands;

use App\Helpers\SqliteDbHelper;
//use App\Helpers\StringHelper;
//use App\Helpers\TelegramHelper;
use App\Helpers\TelegramHelper;
use App\ResourceManager;
use App\Telegram\SpamDetector;
use Exception;
//use Illuminate\Support\Collection;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Objects\Message;

class TelegramBotCommand extends AbstractCommand
{
    private \Aura\Sql\ExtendedPdo $pdo;
    // private \Aura\SqlQuery\QueryFactory $queryFactory;
    private SqliteDbHelper $sqliteDbHelper;

    /**
     * @param ResourceManager $resourceManager
     * @throws Exception
     */
    public function __construct(ResourceManager $resourceManager)
    {
        parent::__construct($resourceManager);
        $this->pdo = $this->resourceManager->getSqliteDb();
        // $this->queryFactory = new \Aura\SqlQuery\QueryFactory('sqlite', \Aura\SqlQuery\QueryFactory::COMMON);
        $this->sqliteDbHelper = new SqliteDbHelper($this->pdo);
    }

    /**
     * @return int|null
     * @throws TelegramSDKException
     * @throws \JsonException
     */
    public function __invoke(): ?int
    {
        $userId = 3305546;
        // $testGroupChatId = 2135599708;
        $didiDighomiChatId = 1677720183;

        $telegram = new \Telegram\Bot\Api($this->resourceManager->getConfig()->get('telegram.bot.token'));

        if (file_exists(PROJECT_ROOT_DIR . '/.group_last_update_id')) {
            $lastUpdateId = json_decode(file_get_contents(PROJECT_ROOT_DIR . '/.group_last_update_id'), false, 512, JSON_THROW_ON_ERROR);
        } else {
            $lastUpdateId = 0;
        }
        echo "Start with lastUpdate = {$lastUpdateId}\n";

        while (true) {
            echo "Get updates...\n";
            try {
                $updates = $telegram->getUpdates(['offset' => $lastUpdateId + 1, 'limit' => 10, 'timeout' => 120]);
                echo "Got " . count($updates) . " update(s)\n";

                if (count($updates) > 0) {
                    foreach ($updates as $update) {
                        echo "\n*** UPDATE {$update->getUpdateId()} ***\n\n";
                        $updateInJson = json_encode($update->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                        echo $updateInJson, "\n";
                        file_put_contents(PROJECT_ROOT_DIR . '/chat-history/' . $update->getUpdateId() . '.json', $updateInJson);
                        $lastUpdateId = $update->getUpdateId();
                        /** @var Message $message */
                        $message = $update->getMessage();
                        $chatId = TelegramHelper::convertChatId($message->chat->id, $message->chat->type);

                        if ($chatId == $didiDighomiChatId) {
                            $groupRow = [
                                'id' => $chatId,
                                'name' => $message->chat->title,
                                'type' => $message->chat->type,
                            ];
                            if ($this->sqliteDbHelper->upsertGroup($groupRow)) {
                                echo "Added new group\n";
                            }

                            if ($message->text !== null) {
                                $messageRow = [
                                    'group_id' => $groupRow['id'],
                                    'id' => $message->messageId,
                                    'date_unixtime' => $message->date,
                                    'edited_unixtime' => $message->editDate,
                                    'from' => TelegramHelper::getMessageFrom($message),
                                    'from_id' => TelegramHelper::getMessageFromId($message),
                                    'username' => $message->from?->username ?? '',
                                    'text' => $message->text,
                                    'forwarded_from' => $message->forwardFromChat?->title,
                                    'reply_to_message_id' => $message->replyToMessage?->messageId,
                                ];
                                if ($this->sqliteDbHelper->upsertMessage($messageRow)) {
                                    echo "Added new message\n";
                                }
                            }

                            $userRow = [
                                'user_id' => TelegramHelper::getMessageFromId($message),
                                'user_int_id' => $message->from?->id,
                                'name' => TelegramHelper::getMessageFrom($message),
                                'is_premium' => (bool) $message->from?->is_premium,
                            ];
                            if (!empty($message->new_chat_member)) {
                                $userRow['is_hidden_join'] = 0;
                            }
                            if ($message->replyToMessage?->messageId !== null) {
                                $userRow['has_replies'] = 1;
                            }
                            $this->sqliteDbHelper->upsertUser($userRow);

                            $spamDetector = new SpamDetector($this->pdo, $message);
                            $spamDetector->rate();
                            echo "Total rate: " . $spamDetector->rate . "\n";

                            echo "Days since first message: " . ($spamDetector->daysSinceFirstMessage !== null ? round($spamDetector->daysSinceFirstMessage, 2) : 'N/A') . "\n";
                            echo "Messages from this user: " . $spamDetector->messagesCountFromUser . "\n";

                            if ($spamDetector->rate < 0 && (float) $spamDetector->daysSinceFirstMessage < 2 && $spamDetector->messagesCountFromUser <= 4) {
                                echo "Ban user {$message->from->id} in chat " . ('-100' . $chatId) . "\n";
                                $telegram->banChatMember([
                                    'chat_id' => '-100' . $chatId,
                                    'user_id' => $message->from->id,
                                    'until_date' => time() + 24 * 3600 * 400, // 400 days = permanent ban
                                ]);
                                sleep(2);
                                echo "Delete message with ID = {$message['message_id']}\n";
                                $telegram->deleteMessage([
                                    'chat_id' => '-100' . $chatId,
                                    'message_id' => $message['message_id'],
                                ]);
                                echo "Send message to bot for {$userId}\n";
                                $telegram->sendMessage([
                                    'chat_id' => $userId,
                                    'text' => 'Удалён спам в чате от ' . TelegramHelper::getMessageFromWithUsername($message) . "\n\n" . $message->text,
                                ]);
                                if ($this->sqliteDbHelper->upsertMessage([
                                    'group_id' => $groupRow['id'],
                                    'id' => $message->messageId,
                                    'is_spam' => 1,
                                ])) {
                                    echo "Marked message as spam\n";
                                }

                                //echo "Send message to chat {$chatId}\n";
                                //$telegram->sendMessage([
                                //    'chat_id' => '-100' . $chatId,
                                //    'text' => 'Удалён спам от [' . StringHelper::escapeMarkdownV2(TelegramHelper::getMessageFrom($message)) . '](tg://user?id='.$message->from->id.') "' . StringHelper::escapeMarkdownV2(StringHelper::trimIfTooLong(StringHelper::filterSpaces($message->text), 75)) . '"',
                                //    'parse_mode' => 'MarkdownV2',
                                //]);
                            }
                        }
                        file_put_contents(PROJECT_ROOT_DIR . '/.group_last_update_id', json_encode($lastUpdateId), LOCK_EX);
                    }
    //                break;
                }
            } catch (TelegramSDKException $e) {
                echo "TELEGRAM ERROR: {$e->getMessage()}\n";
                sleep(5);
                continue;
            }
        }

        //return 0;
    }
}
