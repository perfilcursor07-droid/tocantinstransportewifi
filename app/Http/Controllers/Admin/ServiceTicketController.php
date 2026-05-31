<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceTicket;
use Illuminate\Http\Request;

class ServiceTicketController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status', 'open');

        $tickets = ServiceTicket::when($status !== 'all', fn($q) => $q->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $openCount = ServiceTicket::openCount();
        $closedCount = ServiceTicket::closed()->count();

        return view('admin.tickets.index', compact('tickets', 'status', 'openCount', 'closedCount'));
    }

    public function create()
    {
        $busMap = ServiceTicket::busMap();
        return view('admin.tickets.create', compact('busMap'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'mikrotik_id' => 'nullable|string|max:30',
            'scheduled_date' => 'nullable|date',
        ]);

        $busMap = ServiceTicket::busMap();
        $busNumber = $validated['mikrotik_id'] ? ($busMap[$validated['mikrotik_id']] ?? null) : null;

        ServiceTicket::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'mikrotik_id' => $validated['mikrotik_id'] ?: null,
            'bus_number' => $busNumber,
            'scheduled_date' => $validated['scheduled_date'] ?: null,
            'status' => 'open',
        ]);

        return redirect()->route('admin.tickets.index')->with('success', 'Chamado criado com sucesso!');
    }

    public function close(Request $request, ServiceTicket $ticket)
    {
        $request->validate([
            'resolution' => 'nullable|string|max:5000',
        ]);

        $ticket->update([
            'status' => 'closed',
            'resolution' => $request->resolution,
            'closed_at' => now(),
        ]);

        return redirect()->route('admin.tickets.index')->with('success', 'Chamado encerrado!');
    }

    public function reopen(ServiceTicket $ticket)
    {
        $ticket->update([
            'status' => 'open',
            'resolution' => null,
            'closed_at' => null,
        ]);

        return redirect()->route('admin.tickets.index')->with('success', 'Chamado reaberto!');
    }

    public function destroy(ServiceTicket $ticket)
    {
        $ticket->delete();
        return redirect()->route('admin.tickets.index')->with('success', 'Chamado excluído!');
    }
}
