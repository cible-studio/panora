<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Panel;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Commune;
use App\Models\CampaignPanel;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RapportController extends Controller
{
    public function index(Request $request)
    {
        $annee = (int) ($request->annee ?? date('Y'));
        $moisDu = (int) ($request->mois_du ?? 1);
        $moisAu = (int) ($request->mois_au ?? 12);

        $dateFrom = Carbon::create($annee, $moisDu, 1)->startOfMonth();
        $dateTo = Carbon::create($annee, $moisAu, 1)->endOfMonth();

        $anneesDisponibles = range(date('Y'), max(2020, date('Y') - 5));

        // ── Stats globales ──────────────────────────────────────
        $totalPanneaux = Panel::count();
        $totalClients = Client::count();
        $totalCampagnes = Campaign::whereBetween('start_date', [$dateFrom, $dateTo])->count();

        // ── Occupation globale ──────────────────────────────────
        $occupes = Panel::whereIn('status', ['occupe', 'option', 'confirme'])->count();
        $libres = Panel::where('status', 'libre')->count();
        $maintenance = Panel::where('status', 'maintenance')->count();
        $taux = $totalPanneaux > 0 ? round(($occupes / $totalPanneaux) * 100) : 0;

        $occupation = [
            'taux' => $taux,
            'occupes' => $occupes,
            'libres' => $libres,
            'maintenance' => $maintenance,
            'total' => $totalPanneaux,
        ];

        // ── CA total période ────────────────────────────────────
        $caTotal = Campaign::whereBetween('start_date', [$dateFrom, $dateTo])
            ->sum('total_amount');

        $caTicketMoy = $totalCampagnes > 0 ? round($caTotal / $totalCampagnes) : 0;

        // ── Occupation par commune ──────────────────────────────
        $communes = Commune::withCount(['panels as total_panels'])->get();
        $occParCommune = $communes->filter(fn($c) => $c->total_panels > 0)->map(function ($commune) {
            $total = Panel::where('commune_id', $commune->id)->count();
            $occ = Panel::where('commune_id', $commune->id)
                ->whereIn('status', ['occupe', 'option', 'confirme'])->count();
            $taux = $total > 0 ? round(($occ / $total) * 100) : 0;
            $color = $taux >= 75 ? '#ef4444' : ($taux >= 50 ? '#f97316' : ($taux >= 25 ? '#e8a020' : '#22c55e'));
            return ['commune' => $commune->name, 'total' => $total, 'occupes' => $occ, 'taux' => $taux, 'color' => $color];
        })->sortByDesc('taux')->values();

        // ── Évolution mensuelle (12 derniers mois) ──────────────
        $evolMensuelle = collect();
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $start = $date->copy()->startOfMonth();
            $end = $date->copy()->endOfMonth();
            $total = Panel::count();
            $occ = Campaign::where('status', 'actif')
                ->where('start_date', '<=', $end)
                ->where('end_date', '>=', $start)
                ->withCount('panels')->get()->sum('panels_count');
            $taux = $total > 0 ? min(round(($occ / $total) * 100), 100) : 0;
            $evolMensuelle->push(['label' => $date->format('M'), 'taux' => $taux, 'mois' => $date->month, 'annee' => $date->year]);
        }

        // ── CA mensuel ──────────────────────────────────────────
        $caMensuel = collect();
        for ($m = 1; $m <= 12; $m++) {
            $ca = Campaign::whereYear('start_date', $annee)->whereMonth('start_date', $m)->sum('total_amount');
            $caMensuel->push(['label' => Carbon::create($annee, $m, 1)->format('M'), 'ca' => (float) $ca]);
        }

        // ── Tableau mensuel ─────────────────────────────────────
        $tableauMensuel = collect();
        for ($m = 1; $m <= 12; $m++) {
            $start = Carbon::create($annee, $m, 1)->startOfMonth();
            $end = Carbon::create($annee, $m, 1)->endOfMonth();
            $camps = Campaign::where('start_date', '<=', $end)->where('end_date', '>=', $start)->get();
            $ca = Campaign::whereYear('start_date', $annee)->whereMonth('start_date', $m)->sum('total_amount');
            $panneaux = $camps->sum(fn($c) => $c->panels()->count());
            $taux = $totalPanneaux > 0 ? min(round(($panneaux / $totalPanneaux) * 100), 100) : 0;
            $tableauMensuel->push([
                'mois' => Carbon::create($annee, $m, 1)->format('F Y'),
                'nb_campagnes' => $camps->count(),
                'panneaux_mobilises' => $panneaux,
                'ca' => (float) $ca,
                'taux' => $taux,
            ]);
        }

        // ── Top clients ─────────────────────────────────────────
        $topClients = Client::withCount(['campaigns as nb_campagnes'])
            ->with('campaigns')
            ->get()
            ->map(function ($client) {
                return (object) [
                    'id' => $client->id,
                    'name' => $client->name,
                    'nb_campagnes' => $client->nb_campagnes,
                    'ca_total' => $client->campaigns->sum('total_amount'),
                    'total_panneaux' => $client->campaigns->sum(fn($c) => $c->panels()->count()),
                ];
            })
            ->sortByDesc('ca_total')
            ->take(10)
            ->values();

        // ── Stats communes ──────────────────────────────────────
        $statsCommunes = Commune::withCount('panels')->get()->map(function ($commune) use ($annee) {
            $total = Panel::where('commune_id', $commune->id)->count();
            $occ = Panel::where('commune_id', $commune->id)->whereIn('status', ['occupe', 'option', 'confirme'])->count();
            $libres = Panel::where('commune_id', $commune->id)->where('status', 'libre')->count();
            $maint = Panel::where('commune_id', $commune->id)->where('status', 'maintenance')->count();
            $taux = $total > 0 ? round(($occ / $total) * 100) : 0;
            $tarifMoyen = Panel::where('commune_id', $commune->id)->avg('monthly_rate') ?? 0;
            $caAnnee = Campaign::whereYear('start_date', $annee)
                ->whereHas('panels', fn($q) => $q->where('commune_id', $commune->id))
                ->sum('total_amount');
            return [
                'commune' => $commune->name,
                'total' => $total,
                'occupes' => $occ,
                'libres' => $libres,
                'maintenance' => $maint,
                'taux' => $taux,
                'tarif_moyen' => round($tarifMoyen),
                'ca_annee' => (float) $caAnnee,
            ];
        })->filter(fn($r) => $r['total'] > 0)->sortByDesc('taux')->values();

        // ── Stats clients ───────────────────────────────────────
        $statsClients = Client::with('campaigns')->get()->map(function ($client) {
            $campagnesActives = $client->campaigns->whereIn('status', ['actif', 'pose'])->count();
            $derniere = $client->campaigns->sortByDesc('created_at')->first()?->created_at;
            return [
                'id' => $client->id,
                'name' => $client->name,
                'ncc' => $client->ncc,
                'total_campagnes' => $client->campaigns->count(),
                'campagnes_actives' => $campagnesActives,
                'ca_total' => $client->campaigns->sum('total_amount'),
                'total_panneaux' => $client->campaigns->sum(fn($c) => $c->panels()->count()),
                'derniere_campagne' => $derniere,
            ];
        })->sortByDesc('ca_total')->values();

        // ── Répartition durées ──────────────────────────────────
        $camps = Campaign::whereBetween('start_date', [$dateFrom, $dateTo])->get();
        $durees = ['< 1 mois' => 0, '1-3 mois' => 0, '3-6 mois' => 0, '> 6 mois' => 0];
        foreach ($camps as $c) {
            $j = $c->start_date->diffInDays($c->end_date);
            if ($j < 30)
                $durees['< 1 mois']++;
            elseif ($j < 90)
                $durees['1-3 mois']++;
            elseif ($j < 180)
                $durees['3-6 mois']++;
            else
                $durees['> 6 mois']++;
        }
        $total = array_sum($durees);
        $repartitionDurees = collect($durees)->map(fn($count, $label) => [
            'label' => $label,
            'count' => $count,
            'pct' => $total > 0 ? round(($count / $total) * 100) : 0,
        ])->values();

        // ── Panneaux à décaper (30j) ────────────────────────────
        $aDecaper = collect(DB::select("
            SELECT p.reference, c2.name as commune, cl.name as client_name, cp.end_date,
                   DATEDIFF(cp.end_date, NOW()) as jours_restants
            FROM campaigns cp
            JOIN clients cl ON cl.id = cp.client_id
            LEFT JOIN campaign_panels cpan ON cpan.campaign_id = cp.id
            LEFT JOIN panels p ON p.id = cpan.panel_id
            LEFT JOIN communes c2 ON c2.id = p.commune_id
            WHERE cp.status = 'actif'
              AND cp.end_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)
            ORDER BY cp.end_date ASC
        "));

        return view('admin.rapports.index', compact(
            'annee',
            'moisDu',
            'moisAu',
            'dateFrom',
            'dateTo',
            'anneesDisponibles',
            'totalPanneaux',
            'totalClients',
            'totalCampagnes',
            'occupation',
            'caTotal',
            'caTicketMoy',
            'occParCommune',
            'evolMensuelle',
            'caMensuel',
            'tableauMensuel',
            'topClients',
            'statsCommunes',
            'statsClients',
            'repartitionDurees',
            'aDecaper'
        ));
    }

    public function ajax(Request $request)
    {
        return response()->json(['status' => 'ok']);
    }
}
