<?php

namespace App\Events;

class OrderCreated
{
    public function __construct(
        public readonly array $order
    ) {
    }
}
