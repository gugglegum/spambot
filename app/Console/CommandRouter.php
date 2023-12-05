<?php

declare(strict_types=1);
namespace App\Console;

use App\Console\Commands\HelpCommand;
use App\Console\Commands\ImportHistoryCommand;
use App\Console\Commands\SpamTestCommand;
use App\Console\Commands\TelegramBotCommand;
use App\Console\Commands\TestCommand;

class CommandRouter
{
    public static string $commandClassNamespace = 'App\\Console\\Commands\\';

    public static array $commands = [
        HelpCommand::class => 'Show brief help and list of commands',
        TestCommand::class => 'Just prints Hello',
        TelegramBotCommand::class => 'Telegram bot',
        ImportHistoryCommand::class => 'Import history from Export JSON file into SQLite database',
        SpamTestCommand::class => 'Test spam detector',
    ];

    public static function route(string $command): string
    {
        $commandClass = self::commandToClass($command);
        if (array_key_exists($commandClass, self::$commands)) {
            return $commandClass;
        } else {
            throw new \InvalidArgumentException("Unknown command \"{$command}\" (missing class {$commandClass})");
        }
    }

    public static function commandToClass(string $command): string
    {
        return self::$commandClassNamespace
            . implode('', array_map(function($w) { return ucfirst($w); }, explode('-', $command)))
            . 'Command';
    }

    /**
     * @param string $class
     * @return string
     * @throws \Exception
     */
    public static function classToCommand(string $class): string
    {
        $command = preg_replace('/^' . preg_quote(self::$commandClassNamespace, '/') . '(\w+)Command$/', '$1', $class);
        if ($command != $class) {
            $commandParts = preg_split('/(?=[A-Z])/', $command);
            if ($commandParts[0] == '') {
                array_shift($commandParts);
                for ($i = 0; $i < count($commandParts); $i++) {
                    $commandParts[$i][0] = strtolower($commandParts[$i][0]);
                }
                $command = implode('-', $commandParts);
            } else {
                throw new \Exception("Invalid command class name \"{$class}\" (starts with lowercase)");
            }
        } else {
            throw new \Exception("Invalid command class name \"{$class}\" (missing \"Command\" suffix or wrong namespace)");
        }
        return $command;
    }

}
