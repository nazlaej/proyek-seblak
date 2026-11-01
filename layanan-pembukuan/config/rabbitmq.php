<?php

return [
    'host' => env('RABBITMQ_HOST', 'host.docker.internal'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'seblak'),
    'password' => env('RABBITMQ_PASSWORD', 'seblak_secret'),
    'vhost' => env('RABBITMQ_VHOST', '/'),
];