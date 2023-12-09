<?php

declare(strict_types=1);
namespace App\Console\Commands;

use App\Helpers\SqliteDbHelper;
use App\Helpers\TelegramHelper;
use App\ResourceManager;
use Exception;
use JetBrains\PhpStorm\Pure;

class ImportHistoryCommand extends AbstractCommand
{
    private SqliteDbHelper $sqliteDbHelper;

    /**
     * @param ResourceManager $resourceManager
     * @throws Exception
     */
    public function __construct(ResourceManager $resourceManager)
    {
        parent::__construct($resourceManager);
        $this->sqliteDbHelper = new SqliteDbHelper($this->resourceManager->getSqliteDb());
    }

    public function __invoke(): ?int
    {
        $historyFile = $_SERVER['argv'][2];

        echo "Importing history file \"{$historyFile}\"\n";
        $json = file_get_contents($historyFile);
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        echo "Group: \"{$data['name']}\" (ID: {$data['id']}, type: {$data['type']})\n";
        $groupRow = [
            'id' => $data['id'],
            'name' => $data['name'],
            'type' => $data['type'],
        ];
        if ($this->sqliteDbHelper->upsertGroup($groupRow)) {
            echo "Inserted new group\n";
        }

        $messagesProcessedCounter = 0;
        $messagesAddedCounter = 0;
        $timeStart = gettimeofday(true);
        foreach ($data['messages'] as $message) {

            if ($message['type'] == 'message') {
                $messageRow = [
                    'group_id' => $groupRow['id'],
                    'id' => $message['id'],
                    'date_unixtime' => $message['date_unixtime'],
                    'edited_unixtime' => $message['edited_unixtime'] ?? null,
                    'from' => $message['from'],
                    'from_id' => $message['from_id'],
                    'text' => is_string($message['text']) ? $message['text'] : self::messageTextEntitiesToText($message['text']),
                    'forwarded_from' => $message['forwarded_from'] ?? null,
                    'reply_to_message_id' => $message['reply_to_message_id'] ?? null,
                ];
//                var_dump($messageRow);
                if ($this->sqliteDbHelper->upsertMessage($messageRow)) {
                    $messagesAddedCounter++;
                }

                $userRow = [
                    'user_id' => $message['from_id'],
                    'user_int_id' => TelegramHelper::userIdToInt($message['from_id']),
                    'name' => $message['from'],
                ];
                if (!empty($message['reply_to_message_id'])) {
                    $userRow['has_replies'] = 1;
                }
                $this->sqliteDbHelper->upsertUser($userRow);
            } elseif ($message['type'] == 'service') {
                if ($message['action'] == 'invite_members') {
                    $userRow = [
                        'user_id' => $message['actor_id'],
                        'user_int_id' => TelegramHelper::userIdToInt($message['actor_id']),
                        'name' => $message['actor'],
                    ];
                    if (count($message['members']) == 1 && $message['members'][0] == $message['actor']) {
                        $userRow['is_hidden_join'] = 0;
                    }
                    $this->sqliteDbHelper->upsertUser($userRow);
                }
            }
            $messagesProcessedCounter++;
            if ($messagesProcessedCounter % 1000 == 0) {
                echo "{$messagesProcessedCounter} - " . round(gettimeofday(true) - $timeStart, 1) . " s (" . round($messagesProcessedCounter / (gettimeofday(true) - $timeStart), 1) . " msg/s)\n";
            }
        }
        echo "Messages added: {$messagesAddedCounter}\n";

        return 0;
    }

    private static function messageTextEntitiesToText(array $entities): string
    {
        $text = '';
        foreach ($entities as $entity) {
            if (is_string($entity)) {
                $text .= $entity;
            } else {
                $text .= $entity['text'];
            }
        }
        return $text;
    }
}
