<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceTicket;
use Illuminate\Http\Request;

class ServiceTicketController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'tickets');
        $status = $request->get('status', 'open');

        $tickets = ServiceTicket::when($status !== 'all', fn($q) => $q->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $openCount = ServiceTicket::openCount();
        $closedCount = ServiceTicket::closed()->count();

        $notes = \App\Models\SystemSetting::getValue('admin_notes', '');

        return view('admin.tickets.index', compact('tickets', 'status', 'openCount', 'closedCount', 'tab', 'notes'));
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

    public function edit(ServiceTicket $ticket)
    {
        $busMap = ServiceTicket::busMap();
        return view('admin.tickets.edit', compact('ticket', 'busMap'));
    }

    public function update(Request $request, ServiceTicket $ticket)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'mikrotik_id' => 'nullable|string|max:30',
            'scheduled_date' => 'nullable|date',
        ]);

        $busMap = ServiceTicket::busMap();
        $busNumber = $validated['mikrotik_id'] ? ($busMap[$validated['mikrotik_id']] ?? null) : null;

        $ticket->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'mikrotik_id' => $validated['mikrotik_id'] ?: null,
            'bus_number' => $busNumber,
            'scheduled_date' => $validated['scheduled_date'] ?: null,
        ]);

        return redirect()->route('admin.tickets.index')->with('success', 'Chamado atualizado!');
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

    public function saveNotes(Request $request)
    {
        $request->validate(['notes' => 'nullable|string|max:50000']);
        \App\Models\SystemSetting::setValue('admin_notes', $request->input('notes', ''));
        return redirect()->route('admin.tickets.index', ['tab' => 'notes'])->with('success', 'Anotações salvas!');
    }
}
