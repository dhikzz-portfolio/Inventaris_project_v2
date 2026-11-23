<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Support\Carbon;

class EventStatusService
{
    public function calculateStatus(Event $event, ?Carbon $now = null): EventStatus
    {
        $timezone = config('app.timezone');
        $now = ($now ?? now($timezone))->copy()->setTimezone($timezone)->startOfDay();
        $start = $event->start_date ? Carbon::parse($event->start_date, $timezone)->startOfDay() : null;
        $end = $event->end_date ? Carbon::parse($event->end_date, $timezone)->startOfDay() : ($start ? $start->copy() : null);

        if (! $start) {
            return EventStatus::BELUM_BERJALAN;
        }

        if (! $end) {
            $end = $start->copy();
        }

        if ($now->lt($start)) {
            return EventStatus::BELUM_BERJALAN;
        }

        if ($now->gte($start) && $now->lte($end)) {
            return EventStatus::SEDANG_BERJALAN;
        }

        if ($now->gt($end)) {
            return EventStatus::SUDAH_BERJALAN;
        }

        return EventStatus::BELUM_BERJALAN;
    }

    public function updateStatusIfNeeded(Event $event): void
    {
        $calculated = $this->calculateStatus($event);
        if ($event->status !== $calculated) {
            $event->status = $calculated;
            $event->saveQuietly();
        }
    }
}
