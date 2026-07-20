<?php

return [
    // Webhook rate limit (prevent abuse)
    'webhook' => [
        'max_attempts' => 100,
        'decay_minutes' => 1,
    ],
    // API rate limit for authenticated users
    'api' => [
        'max_attempts' => 60,
        'decay_minutes' => 1,
    ],
];