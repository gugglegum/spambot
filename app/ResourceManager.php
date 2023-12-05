<?php

declare(strict_types=1);

namespace App;

use App\Helpers\SqliteDbHelper;

class ResourceManager
{
    private \Luracast\Config\Config $config;
    private \Aura\Sql\ExtendedPdo $sqliteDb;

    public function getConfig(): \Luracast\Config\Config
    {
        if (!isset($this->config)) {
            $dotenv = new \Dotenv\Dotenv(PROJECT_ROOT_DIR);
            $dotenv->overload();
            $dotenv->required('TELEGRAM_BOT_TOKEN')->notEmpty();
            $dotenv->required('TELEGRAM_BOT_NAME')->notEmpty();
            $dotenv->required('TELEGRAM_BOT_CHAT_ID')->notEmpty();
            $this->config = \Luracast\Config\Config::init(PROJECT_ROOT_DIR . '/config');
        }
        return $this->config;
    }

    /**
     * @return \Aura\Sql\ExtendedPdo
     * @throws \Exception
     */
    public function getSqliteDb(): \Aura\Sql\ExtendedPdo
    {
        if (!isset($this->sqliteDb)) {
            $dbRelativePath = 'db/spambot.sqlite3';
            $sqliteDbFile = PROJECT_ROOT_DIR . '/' . $dbRelativePath;
            $this->sqliteDb = new \Aura\Sql\ExtendedPdo('sqlite:' . $sqliteDbFile);
            $sqliteDbHelper = new SqliteDbHelper($this->sqliteDb);
            if (!file_exists($sqliteDbFile)) {
                echo "Create SQLite database at {$dbRelativePath}\n";
                $sqliteDbHelper->initDb();
            }
            $sqliteDbHelper->rolloverMigrations($sqliteDbFile);
            $this->sqliteDb->exec('PRAGMA foreign_keys=on');
        }
        return $this->sqliteDb;
    }
}
