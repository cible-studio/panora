<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PoseTask;
use App\Models\Panel;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Http\Request;

class PoseController extends Controller
{
    public function index(Request $request)
    {
        $query = PoseTask::with('panel', 'campaign', 'technicien');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('technicien_id')) {
            $query->where('assigned_user_id', $request->technicien_id);
        }

        $poseTasks = $query->latest()->paginate(15)->withQueryString();

        $techniciens = User::where('role', 'technique')->orderBy('name')->get();
        $totalPlanifies = PoseTask::where('status', 'planifiee')->count();
        $totalEnCours = PoseTask::where('status', 'en_cours')->count();
        $totalRealises = PoseTask::where('status', 'realisee')->count();
        $totalAnnules = PoseTask::where('status', 'annulee')->count();

        return view('admin.poses.index', compact(
            'poseTasks',
            'techniciens',
            'totalPlanifies',
            'totalEnCours',
            'totalRealises',
            'totalAnnules'
        ));
    }

    public function create()
    {
        $panels = Panel::orderBy('reference')->get();
        $campaigns = Campaign::where('status', 'actif')->orderBy('name')->get();
        $techniciens = User::where('role', 'technique')->orderBy('name')->get();

        return view('admin.poses.create', compact(
            'panels',
            'campaigns',
            'techniciens'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'panel_id' => 'required|exists:panels,id',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'assigned_user_id' => 'nullable|exists:users,id',
            'team_name' => 'nullable|string|max:50',
            'scheduled_at' => 'required|date',
            'status' => 'required|in:planifiee,en_cours,realisee,annulee',
        ]);

        PoseTask::create($request->all());

        return redirect()->route('admin.pose-tasks.index')
            ->with('success', 'Tâche de pose créée avec succès !');
    }

    public function show(PoseTask $poseTask)
    {
        $poseTask->load('panel', 'campaign', 'technicien');
        return view('admin.poses.show', compact('poseTask'));
    }

    public function edit(PoseTask $poseTask)
    {
        $panels = Panel::orderBy('reference')->get();
        $campaigns = Campaign::where('status', 'actif')->orderBy('name')->get();
        $techniciens = User::where('role', 'technique')->orderBy('name')->get();

        return view('admin.poses.edit', compact(
            'poseTask',
            'panels',
            'campaigns',
            'techniciens'
        ));
    }

    public function update(Request $request, PoseTask $poseTask)
    {
        $request->validate([
            'panel_id' => 'required|exists:panels,id',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'assigned_user_id' => 'nullable|exists:users,id',
            'team_name' => 'nullable|string|max:50',
            'scheduled_at' => 'required|date',
            'status' => 'required|in:planifiee,en_cours,realisee,annulee',
        ]);

        $poseTask->update($request->all());

        return redirect()->route('admin.pose-tasks.index')
            ->with('success', 'Tâche mise à jour !');
    }

    public function destroy(PoseTask $poseTask)
    {
        $poseTask->delete();
        return redirect()->route('admin.pose-tasks.index')
            ->with('success', 'Tâche supprimée !');
    }

    public function markComplete(Request $request, PoseTask $poseTask)
    {
        $poseTask->update([
            'status' => 'realisee',
            'done_at' => now(),
        ]);

        return back()->with('success', 'Pose marquée comme réalisée ! ✅');
    }
}
