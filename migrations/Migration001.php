<?php

class Migration001 extends \App\AbstractMigration
{
    public function __invoke()
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS "groups" (
            "id"	INTEGER,
            "name"	TEXT,
            "type"	TEXT,
            PRIMARY KEY("id")
        )');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS "messages" (
            "group_id"	INTEGER NOT NULL,
            "id"	INTEGER NOT NULL,
            "type"	TEXT NOT NULL,
            "date_unixtime"	INTEGER NOT NULL,
            "edited_unixtime"	INTEGER,
            "actor"	TEXT,
            "actor_id"	TEXT,
            "action"	TEXT,
            "title"	TEXT,
            "from"	TEXT,
            "from_id"	INTEGER,
            "text"	INTEGER,
            "text_entities"	TEXT,
            PRIMARY KEY("group_id","id"),
            FOREIGN KEY("group_id") REFERENCES "groups"("id") ON UPDATE CASCADE ON DELETE CASCADE
        )');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS "date_unixtime" ON "messages" (
            "date_unixtime"
        )');
    }
}
