<?php

return [
    'bot' => [
        'token' => getenv('TELEGRAM_BOT_TOKEN'),
        'name' => getenv('TELEGRAM_BOT_NAME'),
        'chat_id' => getenv('TELEGRAM_BOT_CHAT_ID'),
    ],
];
