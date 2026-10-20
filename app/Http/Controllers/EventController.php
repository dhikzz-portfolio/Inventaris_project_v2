<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\BastService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(): View
    {
        return view('events.index', [
            'events' => Event::withCount('loans')
                ->with(['creator', 'loans' => fn ($q) => $q->withCount('loanItems')])
                ->latest('start_date')
                ->paginate(12),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'pic_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $event = Event::create($data + ['created_by' => $request->user()->id]);

        activity('kegiatan')->performedOn($event)->causedBy($request->user())->log('Membuat kegiatan baru');

        return redirect()->route('events.show', $event)->with('success', 'Kegiatan dibuat. Peminjaman dapat ditautkan lewat form peminjaman.');
    }

    public function show(Event $event): View
    {
        $event->load(['loans.borrower', 'loans.loanItems.item', 'creator']);

        return view('events.show', [
            'event' => $event,
            'bookedItems' => collect($event->bookedItemIds()),
        ]);
    }

    public function downloadBast(Request $request, Event $event, BastService $bast)
    {
        $path = $bast->generateForEvent($event);

        if (! $path) {
            return back()->with('error', 'Belum ada peminjaman aktif pada kegiatan ini untuk dibuatkan BAST.');
        }

        return \Storage::disk('local')->download($path, 'BAST-KEGIATAN-'.$event->id.'.pdf');
    }
}
