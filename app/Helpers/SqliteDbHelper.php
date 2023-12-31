<?php

namespace App\Helpers;

use App\AbstractMigration;

class SqliteDbHelper
{
    private \Aura\Sql\ExtendedPdo $pdo;

    public function __construct(\Aura\Sql\ExtendedPdo $pdo)
    {
        $this->pdo = $pdo;
    }

    public function initDb(): void
    {
        $this->pdo->exec('CREATE TABLE "migrations" (
            "version"	INTEGER NOT NULL,
            "ts"	INTEGER NOT NULL,
            PRIMARY KEY("version")
        )');
    }

    /**
     * @param string $sqliteDbFile
     * @return void
     * @throws \Exception
     */
    public function rolloverMigrations(string $sqliteDbFile): void
    {
        $version = (int) $this->pdo->fetchValue("SELECT IFNULL(MAX(version), 0) FROM migrations");
        do {
            $version++;
            $migrationClassName = "Migration" . str_pad((string) $version, 3, '0', STR_PAD_LEFT);
            $migrationFileName = PROJECT_ROOT_DIR . '/migrations/' . $migrationClassName . '.php';
            if (file_exists($migrationFileName)) {
                echo "Rolling over {$migrationClassName}\n";
                require_once $migrationFileName;
                if (class_exists($migrationClassName)) {
                    $migration = new $migrationClassName($this->pdo);
                    if ($migration instanceof AbstractMigration) {
                        $isNotEmptyDb = ($version > 1) && $this->pdo->fetchValue("SELECT EXISTS(SELECT * FROM messages)");
                        if ($isNotEmptyDb) {
                            copy($sqliteDbFile, $sqliteDbFile . "-backup_before_upgrade_to_version_{$version}");
                        }
                        $migration();
                    } else {
                        throw new \Exception("Found migration {$migrationClassName} but it's not inherited from AbstractMigration");
                    }
                    $this->pdo->exec("INSERT INTO migrations (version, ts) VALUES (
                    " . $this->quote($version) . ",
                    " . $this->quote(time()) . ")");
                } else {
                    throw new \Exception("Found migration {$migrationClassName} but it's not contain class {$migrationClassName}");
                }
            } else {
                break;
            }
        } while ($version < 999);
    }

    public function upsertGroup(array $row): bool
    {
        $cols = ['id'];
        $values = [$this->quote($row['id'])];
        $onConflict = []; // Probably not needed
        if (array_key_exists('name', $row)) {
            $cols[] = 'name';
            $values[] = $this->quoteNullable($row['name']);
            $onConflict[] = 'name = excluded.name';
        }
        if (array_key_exists('type', $row)) {
            $cols[] = 'type';
            $values[] = $this->quoteNullable($row['type']);
            $onConflict[] = 'type = excluded.type';
        }
        $lastRowId = $this->getTableMaxRowId('groups');
        $this->pdo->exec("INSERT INTO groups (" . implode(', ', $cols) . ") VALUES (
                " . implode(', ', $values) . "        
            ) ON CONFLICT (id) DO UPDATE SET
                " . implode(', ', $onConflict)
        );
        return $lastRowId != $this->getTableMaxRowId('groups');
    }

    public function upsertMessage(array $row): bool
    {
        $cols = ['group_id', 'id', 'date_unixtime', 'edited_unixtime', '`from`', 'from_id', 'text', 'forwarded_from', 'reply_to_message_id'];
        $values = [
            $this->quote($row['group_id']),
            $this->quote($row['id']),
            $this->quote($row['date_unixtime']),
            $this->quoteNullable($row['edited_unixtime']),
            $this->quoteNullable($row['from']),
            $this->quoteNullable($row['from_id']),
            $this->quote($row['text']),
            $this->quoteNullable($row['forwarded_from']),
            $this->quoteNullable($row['reply_to_message_id']),
        ];
        $onConflict = [
            'date_unixtime = excluded.date_unixtime',
            'edited_unixtime = excluded.edited_unixtime',
            '`from` = excluded.`from`',
            'from_id = excluded.from_id',
            'text = excluded.text',
            'forwarded_from = excluded.forwarded_from',
            'reply_to_message_id = excluded.reply_to_message_id',
        ];
        if (isset($row['username'])) {
            $cols[] = 'username';
            $values[] = $this->quoteNullable($row['username']);
            $onConflict[] = 'username = excluded.username';
        }
        if (isset($row['is_spam'])) {
            $cols[] = 'is_spam';
            $values[] = $this->quote($row['is_spam']);
            $onConflict[] = 'is_spam = excluded.is_spam';
        }
        $lastRowId = $this->getTableMaxRowId('messages');
        $this->pdo->exec("INSERT INTO messages (" . implode(', ', $cols) . ") VALUES (
                " . implode(', ', $values) . "        
            ) ON CONFLICT (group_id, id) DO UPDATE SET
                " . implode(', ', $onConflict)
        );
        return $lastRowId != $this->getTableMaxRowId('messages');
    }

    public function updateMessage(int $chatId, int $messageId, array $newValues): void
    {
        $updates = [];
        if (isset($newValues['date_unixtime'])) {
            $updates[] = 'date_unixtime = ' . $this->quote($newValues['date_unixtime']);
        }
        if (isset($newValues['edited_unixtime'])) {
            $updates[] = 'edited_unixtime = ' . $this->quoteNullable($newValues['edited_unixtime']);
        }
        if (isset($newValues['from'])) {
            $updates[] = '`from` = ' . $this->quoteNullable($newValues['from']);
        }
        if (isset($newValues['from_id'])) {
            $updates[] = 'from_id = ' . $this->quoteNullable($newValues['from_id']);
        }
        if (isset($newValues['username'])) {
            $updates[] = 'username = ' . $this->quoteNullable($newValues['username']);
        }
        if (isset($newValues['text'])) {
            $updates[] = 'text = ' . $this->quote($newValues['text']);
        }
        if (isset($newValues['forwarded_from'])) {
            $updates[] = 'forwarded_from = ' . $this->quoteNullable($newValues['forwarded_from']);
        }
        if (isset($newValues['reply_to_message_id'])) {
            $updates[] = 'reply_to_message_id = ' . $this->quoteNullable($newValues['reply_to_message_id']);
        }
        if (isset($newValues['is_spam'])) {
            $updates[] = 'is_spam = ' . $this->quote($newValues['is_spam']);
        }

        $stmt = $this->pdo->prepare("UPDATE messages SET
            " . implode(', ', $updates) . "        
            WHERE `group_id` = :group_id AND `id` = :message_id"
        );
        $stmt->execute([
            'group_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    public function isMessageExists(int $chatId, int $messageId): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) `count` FROM `messages` WHERE `group_id` = :group_id AND `id` = :message_id");
        $stmt->execute([
            'group_id' => $chatId,
            'message_id' => $messageId,
        ]);
        $count = $stmt->fetchColumn();
        $stmt->closeCursor();
        return $count != 0;
    }

    public function upsertUser(array $row): bool
    {
        $cols = ['user_id', 'user_int_id', 'name'];
        $values = [$this->quote($row['user_id']), $this->quoteNullable($row['user_int_id']), $this->quote($row['name'])];
        $onConflict = ['user_id = excluded.user_id' /* Probably not needed */, 'user_int_id = excluded.user_int_id', 'name = excluded.name'];
        if (array_key_exists('is_premium', $row)) {
            $cols[] = 'is_premium';
            $values[] = $this->quoteNullable($row['is_premium']);
            $onConflict[] = 'is_premium = excluded.is_premium';
        }
        if (array_key_exists('is_hidden_join', $row)) {
            $cols[] = 'is_hidden_join';
            $values[] = $this->quoteNullable($row['is_hidden_join']);
            $onConflict[] = 'is_hidden_join = excluded.is_hidden_join';
        }
        if (array_key_exists('id_diff_from_highest', $row)) {
            $cols[] = 'id_diff_from_highest';
            $values[] = $this->quoteNullable($row['id_diff_from_highest']);
            //$onConflict[] = 'id_diff_from_highest = excluded.id_diff_from_highest';
        }
        if (array_key_exists('has_replies', $row)) {
            $cols[] = 'has_replies';
            $values[] = $this->quoteNullable($row['has_replies']);
            $onConflict[] = 'has_replies = excluded.has_replies';
        }

        $lastRowId = $this->getTableMaxRowId('users');
        $this->pdo->exec("INSERT INTO users (" . implode(', ', $cols) . ") VALUES (
                " . implode(', ', $values) . "        
            ) ON CONFLICT (user_id) DO UPDATE SET
                " . implode(', ', $onConflict)
        );
        return $lastRowId != $this->getTableMaxRowId('users');
    }

    public function getTableMaxRowId(string $table): int
    {
        return (int) $this->pdo->fetchValue("SELECT MAX(rowid) FROM `{$table}`");
    }
    
    public function quote($value): string
    {
        if (is_bool($value)) {
            $value = (int) $value;
        }
        if (is_string($value) || is_null($value)) {
//            $value = "'" . str_replace("'", "''", $value) . "'";
            $value = $this->pdo->quote($value);
        }
        return $value;
    }

    public function quoteNullable($value): string
    {
        if (is_null($value)) {
            $value = 'NULL';
        } else {
            $value = $this->quote($value);
        }
        return $value;
    }
}
