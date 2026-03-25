<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Panel;
use App\Models\Reservation;
use App\Models\ReservationPanel;
use App\Models\Client;
use App\Enums\ReservationStatus;
use Illuminate\Support\Facades\DB;


class PanelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    // Dans app/Http/Controllers/Admin/PanelController.php

    public function quickDetails(Panel $panel)
    {
        $now = now()->startOfDay();
        
        // Occupation en cours
        $current = DB::table('reservation_panels')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->join('clients', 'clients.id', '=', 'reservations.client_id')
            ->where('reservation_panels.panel_id', $panel->id)
            ->where('reservations.start_date', '<=', $now)
            ->where('reservations.end_date', '>=', $now)
            ->whereIn('reservations.status', ['en_attente', 'confirme'])
            ->select('clients.name as client_name', 'reservations.start_date', 'reservations.end_date', 'reservations.status')
            ->first();
        
        // Prochaine occupation
        $next = null;
        if (!$current) {
            $next = DB::table('reservation_panels')
                ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
                ->join('clients', 'clients.id', '=', 'reservations.client_id')
                ->where('reservation_panels.panel_id', $panel->id)
                ->where('reservations.start_date', '>', $now)
                ->whereIn('reservations.status', ['en_attente', 'confirme'])
                ->orderBy('reservations.start_date')
                ->select('clients.name as client_name', 'reservations.start_date', 'reservations.end_date')
                ->first();
        }
        
        return response()->json([
            'current_occupation' => $current ? [
                'client_name' => $current->client_name,
                'start_date' => $current->start_date,
                'end_date' => $current->end_date,
                'status' => $current->status === 'confirme' ? 'confirme' : 'option'
            ] : null,
            'next_occupation' => $next ? [
                'client_name' => $next->client_name,
                'start_date' => $next->start_date,
                'end_date' => $next->end_date,
            ] : null,
        ]);
    }

}
