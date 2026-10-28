<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\EventStatusService;
use Illuminate\Console\Command;

class UpdateEventStatuses extends Command
{
    protected $signature = 'events:update-statuses';

    protected $description = 'Update event statuses automatically based on current date';

    public function handle(EventStatusService $service): int
    {
        $events = Event::all();
        foreach ($events as $event) {
            $service->updateStatusIfNeeded($event);
        }

        $this->info('Event statuses updated.');
        return self::SUCCESS;
    }
}
