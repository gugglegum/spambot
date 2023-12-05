<?php

declare(strict_types=1);
namespace App\Console\Commands;

class TestCommand extends AbstractCommand
{
    private \Aura\Sql\ExtendedPdo $pdo;

    public function __invoke(): ?int
    {
//        echo "hello\n";

        date_default_timezone_set('Asia/Tbilisi');
//        var_dump(date_default_timezone_get());die;


        $this->pdo = $this->resourceManager->getSqliteDb();

        $didiDighomiChatId = 1677720183;
        $stmt = $this->pdo->prepare("SELECT * FROM `messages` WHERE `group_id` = :group_id AND date_unixtime < :from_date_unixtime");
        $stmt->execute([
            'group_id' => $didiDighomiChatId,
//            'from_date_unixtime' => (new \DateTime('2023-05-31T05:00:00'))->getTimestamp(),
//            'from_date_unixtime' => (new \DateTime('2023-10-01T05:00:00'))->getTimestamp(),
            'from_date_unixtime' => (new \DateTime('2023-06-01T05:00:00'))->getTimestamp(),
        ]);
        $messages = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $message = [
                'msg_id' => $row['id'],
                'datetime' => date('Y-m-d H:i:s', (int) $row['date_unixtime']),
//                'unixtime' => (int) $row['date_unixtime'],
                'from' => $row['from'],
                //'from_id' => $row['from_id'],
                'text' => $row['text'],
            ];
            if (!empty($row['reply_to_message_id'])) {
                $message['reply_msg_id'] = $row['reply_to_message_id'];
            }

            $messages[] = $message;
            echo json_encode($message, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        }
        $stmt->closeCursor();
        file_put_contents($_SERVER['argv'][2], json_encode($messages, /*JSON_PRETTY_PRINT |*/ JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));


//        $data = json_decode(file_get_contents($_SERVER['argv'][2]), true, JSON_THROW_ON_ERROR);
//
//        for ($i = 0; $i < count($data['messages']); $i++) {
//            if ($data['messages'][$i]['type'] != 'message') {
//                unset($data['messages'][$i]);
//            }
//        }

//        file_put_contents($_SERVER['argv'][3], json_encode($data, /*JSON_PRETTY_PRINT |*/ JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return 0;
    }
}
