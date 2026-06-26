<x-admin-layout title="Poses oubliées">

<x-slot:topbarLeft>
    <a href="{{ route('admin.pose-tasks.index') }}" class="btn btn-ghost btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Retour Poses
    </a>
</x-slot:topbarLeft>

{{-- ════ BANDEAU EXPLICATIF ════ --}}
<div style="background:linear-gradient(180deg,#fff7ed,#fffbeb);border:1px solid #fed7aa;border-radius:14px;padding:16px 20px;margin-bottom:18px;display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap">
    <div style="width:44px;height:44px;border-radius:11px;background:#f97316;color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0">🕒</div>
    <div style="flex:1;min-width:280px">
        <div style="font-weight:800;color:#9a3412;font-size:15px;margin-bottom:4px">
            {{ $totalOubliees }} pose(s) oubliée(s) au total
        </div>
        <div style="font-size:12.5px;color:#9a3412;line-height:1.5">
            Ces poses sont <strong>planifiées dans le passé</strong> mais n'ont jamais été marquées
            réalisées sur la plateforme. Si elles ont été <strong>faites sur le terrain</strong>,
            sélectionne-les et utilise « Marquer réalisées » pour rattraper le retard de saisie.
        </div>
    </div>
</div>

{{-- ════ FLASH SUCCESS / ERROR ════ --}}
@if(session('success'))
<div style="background:#dcfce7;border:1px solid #86efac;border-radius:10px;padding:10px 14px;margin-bottom:14px;color:#166534;font-weight:600;font-size:13px">
    {{ session('success') }}
</div>
@endif
@if($errors->any())
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:10px;padding:10px 14px;margin-bottom:14px;color:#991b1b;font-size:13px">
    @foreach($errors->all() as $error)<div>• {{ $error }}</div>@endforeach
</div>
@endif

{{-- ════ FILTRES ════ --}}
<form method="GET" action="{{ route('admin.pose-tasks.oubliees') }}"
      style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px 16px;margin-bottom:14px;display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
    <div style="flex:1;min-width:160px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:4px">Technicien / MP</label>
        <select name="user_id" onchange="this.form.submit()" class="form-input" style="width:100%;padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px">
            <option value="">— Tous —</option>
            @foreach($users as $u)
                <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
            @endforeach
        </select>
    </div>
    <div style="flex:1;min-width:160px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:4px">Client</label>
        <select name="client_id" onchange="this.form.submit()" class="form-input" style="width:100%;padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px">
            <option value="">— Tous —</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" {{ request('client_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div style="min-width:140px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:4px">Mois prévu</label>
        <input type="month" name="month" value="{{ request('month') }}" onchange="this.form.submit()"
               style="width:100%;padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px">
    </div>
    @if(request()->hasAny(['user_id', 'client_id', 'month']))
        <a href="{{ route('admin.pose-tasks.oubliees') }}" class="btn btn-ghost btn-sm" style="height:fit-content">↺ Réinitialiser</a>
    @endif
</form>

{{-- ════ TABLEAU + BULK ACTION ════ --}}
<form id="bulk-form" method="POST" action="{{ route('admin.pose-tasks.bulk-complete-oubliees') }}">
    @csrf
    {{-- Champs cachés pour préserver les filtres après l'action --}}
    @foreach(['user_id', 'client_id', 'month'] as $f)
        @if(request($f))<input type="hidden" name="{{ $f }}" value="{{ request($f) }}">@endif
    @endforeach

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden">
        @if($tasks->isEmpty())
            <div style="padding:60px 20px;text-align:center;color:#6b7280">
                <div style="font-size:48px;margin-bottom:8px">🎉</div>
                <div style="font-size:14px;font-weight:700">Aucune pose oubliée !</div>
                <div style="font-size:12px;margin-top:4px">
                    @if(request()->hasAny(['user_id', 'client_id', 'month']))
                        Avec ces filtres. <a href="{{ route('admin.pose-tasks.oubliees') }}" style="color:#e8a020">Voir tout</a>
                    @else
                        Bravo, tout est à jour.
                    @endif
                </div>
            </div>
        @else
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead>
                    <tr style="background:#f8fafc;text-align:left">
                        <th style="padding:10px 12px;width:36px">
                            <input type="checkbox" id="select-all" style="cursor:pointer">
                        </th>
                        <th style="padding:10px 12px;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.3px">Panneau</th>
                        <th style="padding:10px 12px;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.3px">Campagne / Client</th>
                        <th style="padding:10px 12px;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.3px">Technicien</th>
                        <th style="padding:10px 12px;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.3px">Prévu le</th>
                        <th style="padding:10px 12px;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.3px">Retard</th>
                        <th style="padding:10px 12px;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.3px">Statut</th>
                        <th style="padding:10px 12px;width:48px"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tasks as $t)
                        @php
                            $retardJours = $t->scheduled_at?->diffInDays(now());
                            $statusEnum = \App\Enums\PoseTaskStatus::tryFrom($t->status);
                        @endphp
                        <tr style="border-top:1px solid #f1f5f9">
                            <td style="padding:10px 12px">
                                <input type="checkbox" name="task_ids[]" value="{{ $t->id }}" class="task-check" style="cursor:pointer">
                            </td>
                            <td style="padding:10px 12px;font-family:monospace;font-weight:700;color:#0a0c10">
                                {{ $t->panel?->reference ?? '—' }}
                                <div style="font-size:11px;color:#9ca3af;font-weight:400;font-family:inherit">{{ $t->panel?->commune?->name ?? '' }}</div>
                            </td>
                            <td style="padding:10px 12px">
                                @if($t->campaign)
                                    <a href="{{ route('admin.campaigns.show', $t->campaign) }}" style="color:#0a0c10;font-weight:600;text-decoration:none">{{ $t->campaign->name }}</a>
                                    <div style="font-size:11px;color:#6b7280">{{ $t->campaign->client?->name ?? '—' }}</div>
                                @else
                                    <span style="color:#9ca3af;font-style:italic">Sans campagne</span>
                                @endif
                            </td>
                            <td style="padding:10px 12px;font-size:12px;color:#475569">
                                {{ $t->technicianDisplay()['name'] }}
                            </td>
                            <td style="padding:10px 12px;font-size:12px">
                                {{ $t->scheduled_at?->format('d/m/Y') ?? '—' }}
                                <div style="font-size:11px;color:#9ca3af">{{ $t->scheduled_at?->format('H:i') }}</div>
                            </td>
                            <td style="padding:10px 12px">
                                @if($retardJours !== null)
                                    <span style="display:inline-block;padding:3px 10px;background:rgba(239,68,68,.1);color:#dc2626;border-radius:20px;font-size:11px;font-weight:700">
                                        J+{{ (int) $retardJours }}
                                    </span>
                                @endif
                            </td>
                            <td style="padding:10px 12px">
                                @if($statusEnum)
                                    <span style="display:inline-block;padding:3px 10px;background:{{ $statusEnum->color() }}1a;color:{{ $statusEnum->color() }};border-radius:20px;font-size:11px;font-weight:700">
                                        {{ $statusEnum->icon() }} {{ $statusEnum->label() }}
                                    </span>
                                @endif
                            </td>
                            <td style="padding:10px 12px;text-align:right">
                                <a href="{{ route('admin.pose-tasks.show', $t) }}" title="Voir le détail"
                                   style="color:#6b7280;text-decoration:none;font-size:14px">↗</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="padding:10px 14px;border-top:1px solid #f1f5f9;background:#fafbfc">
            {{ $tasks->links() }}
        </div>
        @endif
    </div>

    {{-- ════ BARRE D'ACTIONS FLOTTANTE ════ --}}
    <div id="bulk-bar" style="display:none;position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#0a0c10;color:#fff;padding:14px 22px;border-radius:14px;box-shadow:0 10px 40px rgba(0,0,0,.3);z-index:1000;align-items:center;gap:16px">
        <span id="bulk-count" style="font-weight:700;font-size:13px"></span>
        <button type="button" onclick="document.getElementById('confirm-modal').style.display='flex'"
                style="padding:9px 18px;background:#22c55e;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer">
            ✓ Marquer réalisées
        </button>
        <button type="button" onclick="clearSelection()"
                style="padding:9px 14px;background:transparent;color:#9ca3af;border:1px solid #374151;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer">
            Annuler
        </button>
    </div>

    {{-- ════ MODAL CONFIRMATION + DATE ════ --}}
    <div id="confirm-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.6);backdrop-filter:blur(2px);align-items:center;justify-content:center;padding:16px">
        <div style="background:#fff;border-radius:14px;max-width:440px;width:100%;padding:24px;box-shadow:0 25px 50px rgba(0,0,0,.25)">
            <div style="font-size:17px;font-weight:800;color:#0a0c10;margin-bottom:8px">
                ✓ Marquer comme réalisées
            </div>
            <div style="font-size:13px;color:#6b7280;line-height:1.5;margin-bottom:18px">
                <span id="modal-count"></span>
                <br><br>
                <strong>Date réelle de réalisation</strong> sur le terrain
                (par défaut aujourd'hui — passe à la date passée si les poses ont été faites avant).
            </div>
            <input type="date" name="done_at" value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" required
                   style="width:100%;padding:11px 13px;border:1.5px solid #e5e7eb;border-radius:9px;font-size:14px;margin-bottom:18px">
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" onclick="document.getElementById('confirm-modal').style.display='none'"
                        style="padding:10px 18px;background:#fff;color:#6b7280;border:1px solid #e5e7eb;border-radius:9px;font-weight:600;font-size:13px;cursor:pointer">
                    Annuler
                </button>
                <button type="submit"
                        style="padding:10px 22px;background:#22c55e;color:#fff;border:none;border-radius:9px;font-weight:700;font-size:13px;cursor:pointer">
                    Confirmer
                </button>
            </div>
        </div>
    </div>
</form>

<script>
(function() {
    const checks = document.querySelectorAll('.task-check');
    const selectAll = document.getElementById('select-all');
    const bar = document.getElementById('bulk-bar');
    const countSpan = document.getElementById('bulk-count');
    const modalCount = document.getElementById('modal-count');

    function refresh() {
        const checked = Array.from(checks).filter(c => c.checked);
        const n = checked.length;
        if (n > 0) {
            bar.style.display = 'flex';
            const label = `${n} pose${n > 1 ? 's' : ''} sélectionnée${n > 1 ? 's' : ''}`;
            countSpan.textContent = label;
            modalCount.textContent = `Tu vas marquer ${label} comme réalisée${n > 1 ? 's' : ''}.`;
        } else {
            bar.style.display = 'none';
        }
        if (selectAll) selectAll.checked = n > 0 && n === checks.length;
    }
    checks.forEach(c => c.addEventListener('change', refresh));
    if (selectAll) selectAll.addEventListener('change', () => {
        checks.forEach(c => c.checked = selectAll.checked);
        refresh();
    });

    window.clearSelection = function() {
        checks.forEach(c => c.checked = false);
        refresh();
    };
})();
</script>

</x-admin-layout>
