<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Telegram journal bot (php artisan telegram:poll). Only chat_id is answered, and its
    // messages are logged as user_id.
    'telegram' => [
        'token'   => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
        'user_id' => env('TELEGRAM_USER_ID'),
        // The bot's "now" and "today"; times are stored in app.timezone.
        'timezone' => env('TELEGRAM_TIMEZONE', 'Europe/London'),
        // Optional notes about the user given to Claude with every message. Keep it out of git.
        'about_file' => env('TELEGRAM_ABOUT_FILE', storage_path('app/private/bot-about.md')),
        // What the bot could not log, one JSON line each; `bot:pull-requests` on the PC
        // (BOT_SERVER over SSH) turns them into cards in board_path.
        'requests_file' => env('TELEGRAM_REQUESTS_FILE', storage_path('app/private/feature-requests.jsonl')),
        'server' => env('BOT_SERVER', 'rob@192.168.222.134'),
        'server_requests_file' => env('BOT_SERVER_REQUESTS_FILE', '/opt/biotracker/storage/app/private/feature-requests.jsonl'),
        'board_path' => base_path('docs/board'),
        // Where the evening pollen check (telegram:pollen) looks. Default: Brighton.
        'latitude' => env('TELEGRAM_LATITUDE', 50.82),
        'longitude' => env('TELEGRAM_LONGITUDE', -0.10),
    ],

    // Claude Code CLI, logged in to the subscription: no API key.
    'claude' => [
        'bin'   => env('CLAUDE_BIN', 'claude'),
        'model' => env('CLAUDE_MODEL', 'haiku'),
    ],

    // Phone push when the bot has been offline 15 minutes. The topic is a secret: anyone who
    // knows it can read and send. Shared with Rob's other projects; empty turns pushes off.
    'ntfy' => [
        'topic' => env('NTFY_TOPIC'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
