<?php

namespace App\Console\Commands;

use App\Models\MailMessage;
use App\Models\Task;
use App\Models\Ticket;
use App\Support\Mail\MimeHeaderDecoder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class DecodeMailSubjectsCommand extends Command
{
    /** @var string */
    protected $signature = 'tickets:decode-mail-subjects';

    /** @var string */
    protected $description = 'Decodifica los encabezados MIME ya importados en mensajes, tickets y tareas';

    /**
     * Repair subjects stored before MIME header decoding was in place.
     */
    public function handle(): int
    {
        $messages = $this->decodeColumn(MailMessage::class, 'subject');
        $senders = $this->decodeColumn(MailMessage::class, 'from_name');
        $tickets = $this->decodeColumn(Ticket::class, 'subject');
        $tasks = $this->decodeColumn(Task::class, 'title');

        $this->info("Mensajes actualizados: {$messages}");
        $this->info("Remitentes actualizados: {$senders}");
        $this->info("Tickets actualizados: {$tickets}");
        $this->info("Tareas actualizadas: {$tasks}");

        return self::SUCCESS;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function decodeColumn(string $modelClass, string $column): int
    {
        $updated = 0;

        $modelClass::query()
            ->where($column, 'like', '%=?%?=%')
            ->chunkById(100, function (Collection $records) use ($column, &$updated): void {
                foreach ($records as $record) {
                    $decoded = MimeHeaderDecoder::decode($record->{$column});

                    if ($decoded === null || $decoded === $record->{$column}) {
                        continue;
                    }

                    $record->update([$column => $decoded]);
                    $updated++;
                }
            });

        return $updated;
    }
}
