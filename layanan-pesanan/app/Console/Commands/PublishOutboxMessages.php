<?php

namespace App\Console\Commands;

use App\Models\OutboxMessage;
use App\Services\OrderPublisher;
use Illuminate\Console\Command;

class PublishOutboxMessages extends Command
{
    protected $signature = 'outbox:publish {--limit=100 : Maksimum pesan yang dicoba dalam satu proses}';

    protected $description = 'Mengirim event outbox yang belum dikonfirmasi RabbitMQ';

    public function handle(OrderPublisher $publisher): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $messages = OutboxMessage::query()
            ->whereNull('published_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $failed = 0;

        foreach ($messages as $message) {
            if ($publisher->publish($message)) {
                $this->info("Event {$message->event_id} terkirim.");
            } else {
                $failed++;
                $this->error("Event {$message->event_id} belum terkirim; akan dicoba lagi.");
            }
        }

        $this->info('Pesan tertunda ditemukan: '.$messages->count().'; gagal: '.$failed.'.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
