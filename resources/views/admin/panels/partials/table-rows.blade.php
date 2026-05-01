{{-- resources/views/admin/panels/partials/table-rows.blade.php --}}
@if($source !== 'externe')
@forelse($panels as $panel)
<tr>
    <td>
        @php
            $photo = $panel->photos->first();
            $photoPath = $photo ? storage_path('app/public/'.$photo->path) : null;
            $hasFile = $photo && file_exists($photoPath);
        @endphp
        @if($hasFile)
            <img src="{{ asset('storage/'.$photo->path) }}"
                 alt="{{ $panel->reference }}"
                 loading="lazy"
                 onerror="this.onerror=null;this.src='/images/panel-placeholder.svg';"
                 style="width:60px;height:45px;object-fit:cover;border-radius:6px;border:1px solid var(--border);">
        @else
            <img src="/images/panel-placeholder.svg" alt="placeholder"
                 style="width:60px;height:45px;object-fit:cover;border-radius:6px;border:1px solid var(--border);background:var(--surface2);">
        @endif
    </td>
    <td><span style="font-family:monospace;color:var(--accent);font-weight:700;">{{ $panel->reference }}</span></td>
    <td><div style="font-weight:500;">{{ $panel->name }}</div><div style="font-size:11px;color:var(--text3);">{{ $panel->category?->name ?? '—' }} @if($panel->is_lit) · 💡 @endif</div></td>
    <td>{{ $panel->commune->name }}</td>
    <td><div>{{ $panel->format->name }}</div>@if($panel->format->surface)<div style="font-size:11px;color:var(--text3);">{{ $panel->format->surface }}m²</div>@endif</td>
    <td style="text-align:center;"><span style="font-weight:700;color:var(--text2);">{{ $panel->nombre_faces ?? 1 }}</span></td>
    <td>@if($panel->quartier)<div style="font-weight:500;font-size:12px;">{{ $panel->quartier }}</div>@endif @if($panel->adresse)<div style="font-size:11px;color:var(--text3);">{{ $panel->adresse }}</div>@endif @if(!$panel->quartier && !$panel->adresse)<span style="color:var(--text3);">—</span>@endif</td>
    <td style="color:var(--accent);font-weight:600;">{{ number_format($panel->monthly_rate, 0, ',', ' ') }} FCFA</td>
    <td>@if($panel->status->value === 'libre')<span class="badge badge-green">Libre</span>@elseif($panel->status->value === 'option')<span class="badge badge-orange">Option</span>@elseif($panel->status->value === 'confirme')<span class="badge badge-blue">Confirmé</span>@elseif($panel->status->value === 'occupe')<span class="badge badge-purple">Occupé</span>@else<span class="badge badge-red">Maintenance</span>@endif</td>
    <td><div style="display:flex;gap:6px;"><a href="{{ route('admin.panels.show', $panel) }}" class="btn btn-ghost btn-sm" title="Voir">👁️</a><a href="{{ route('admin.panels.edit', $panel) }}" class="btn btn-ghost btn-sm" title="Modifier">✏️</a><a href="{{ route('admin.panels.pdf', $panel) }}" class="btn btn-ghost btn-sm" title="PDF">📄</a><form method="POST" action="{{ route('admin.panels.destroy', $panel) }}" onsubmit="return confirm('Supprimer ce panneau ?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm" title="Supprimer">🗑️</button></form></div></td>
</tr>
@empty
@if($source === 'cible' || ($externalPanels ?? collect())->isEmpty())
<tr><td colspan="11" style="text-align:center;color:var(--text3);padding:32px;">Aucun panneau trouvé</td></tr>
@endif
@endforelse
@endif

{{-- PANNEAUX EXTERNES --}}
@if($source !== 'cible' && isset($externalPanels) && $externalPanels->isNotEmpty())
@if($source === 'all' && ($panels ?? collect())->isNotEmpty())
<tr><td colspan="11" style="padding:8px 12px;background:rgba(168,85,247,0.06);border-top:2px solid rgba(168,85,247,0.3);border-bottom:1px solid rgba(168,85,247,0.2);"><span style="font-size:11px;font-weight:700;color:var(--purple);text-transform:uppercase;letter-spacing:1px;">🏢 Panneaux — Régies externes ({{ $externalPanels->count() }})</span></td></tr>
@endif

@foreach($externalPanels as $ext)
<tr style="background:rgba(168,85,247,0.02);">
    <td><div style="width:60px;height:45px;border-radius:6px;border:1px solid rgba(168,85,247,0.2);background:rgba(168,85,247,0.08);display:flex;align-items:center;justify-content:center;color:var(--purple);font-size:16px;">🏢</div></td>
    <td><span style="font-family:monospace;color:var(--purple);font-weight:700;">{{ $ext->code_panneau }}</span><div style="margin-top:2px;"><span style="font-size:10px;padding:1px 6px;border-radius:4px;background:rgba(168,85,247,0.12);color:var(--purple);font-weight:600;">{{ $ext->agency->name }}</span></div></td>
    <td><div style="font-weight:500;">{{ $ext->designation }}</div><div style="font-size:11px;color:var(--text3);">{{ $ext->category?->name ?? '—' }} @if($ext->is_lit) · 💡 @endif</div></td>
    <td>{{ $ext->commune?->name ?? '—' }}</td>
    <td><div>{{ $ext->format?->name ?? '—' }}</div>@if($ext->format?->surface)<div style="font-size:11px;color:var(--text3);">{{ $ext->format->surface }}m²</div>@endif</td>
    <td style="text-align:center;"><span style="font-weight:700;color:var(--text2);">{{ $ext->nombre_faces ?? 1 }}</span></td>
    <td>@if($ext->quartier)<div style="font-weight:500;font-size:12px;">{{ $ext->quartier }}</div>@endif @if($ext->adresse)<div style="font-size:11px;color:var(--text3);">{{ $ext->adresse }}</div>@endif @if(!$ext->quartier && !$ext->adresse)<span style="color:var(--text3);">—</span>@endif @if($ext->orientation)<span class="badge badge-gray mt-1">{{ ucfirst($ext->orientation) }}</span>@endif</td>
    <td style="color:var(--purple);font-weight:600;">@if($ext->monthly_rate > 0){{ number_format($ext->monthly_rate, 0, ',', ' ') }} FCFA @else<span style="color:var(--text3);">—</span>@endif</td>
    <td><span style="font-size:11px;padding:2px 8px;border-radius:20px;background:rgba(168,85,247,0.12);color:var(--purple);border:1px solid rgba(168,85,247,0.3);font-weight:600;">🏢 Externe</span></td>
    <td><a href="{{ route('admin.external-agencies.show', $ext->agency_id) }}" class="btn btn-ghost btn-sm" title="Voir la régie">👁️</a></td>
</tr>
@endforeach
@endif