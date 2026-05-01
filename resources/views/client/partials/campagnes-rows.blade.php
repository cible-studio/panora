@forelse($campagnes as $camp)
@php
    $s = $camp->status->value;
    $badge = match($s) {
        'actif'    => ['bg'=>'rgba(34,197,94,.1)',  'color'=>'#22c55e',  'label'=>'Actif'],
        'pose'     => ['bg'=>'rgba(59,130,246,.1)', 'color'=>'#60a5fa',  'label'=>'En pose'],
        'planifie' => ['bg'=>'rgba(63,127,192,.1)', 'color'=>'#3f7fc0',  'label'=>'Planifiée'],
        'termine'  => ['bg'=>'rgba(250,184,11,.1)', 'color'=>'#fab80b',  'label'=>'Terminée'],
        'annule'   => ['bg'=>'rgba(239,68,68,.1)',  'color'=>'#ef4444',  'label'=>'Annulée'],
        default    => ['bg'=>'rgba(148,163,184,.1)','color'=>'#94a3b8',  'label'=>ucfirst($s)],
    };
@endphp
<tr style="border-bottom:1px solid var(--border);transition:background .1s;"
    onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background=''">
    <td style="padding:14px 16px;">
        <span style="font-weight:600;font-size:14px;color:var(--text);">{{ $camp->name }}</span>
    </td>
    <td style="padding:14px 16px;font-size:12px;color:var(--text2);white-space:nowrap;">
        {{ $camp->start_date->format('d/m/Y') }} → {{ $camp->end_date->format('d/m/Y') }}
    </td>
    <td style="padding:14px 16px;font-size:13px;color:var(--text2);">
        {{ $camp->panels_count }}
    </td>
    <td style="padding:14px 16px;font-size:13px;font-weight:700;color:#e20613;white-space:nowrap;">
        {{ number_format($camp->total_amount ?? 0, 0, ',', ' ') }} FCFA
    </td>
    <td style="padding:14px 16px;">
        <span style="font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;background:{{ $badge['bg'] }};color:{{ $badge['color'] }};">
            {{ $badge['label'] }}
        </span>
    </td>
    <td style="padding:14px 16px;white-space:nowrap;">
        <a href="{{ route('client.campagne.detail', $camp) }}"
           style="font-size:12px;font-weight:600;color:#e20613;text-decoration:none;transition:opacity .15s;"
           onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'">
            Voir →
        </a>
        @if($s === 'termine' && $camp->satisfactionSurvey)
            @if($camp->satisfactionSurvey->isCompleted())
            <span style="margin-left:8px;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;background:rgba(34,197,94,.1);color:#22c55e;">
                ★ {{ number_format($camp->satisfactionSurvey->averageScore() ?? 0, 1) }}/5
            </span>
            @else
            <a href="{{ $camp->satisfactionSurvey->publicUrl() }}"
               style="margin-left:8px;font-size:11px;font-weight:600;color:#fab80b;text-decoration:none;transition:opacity .15s;"
               onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'">
                ★ Évaluer
            </a>
            @endif
        @endif
    </td>
</tr>
@empty
<tr>
    <td colspan="6" style="padding:60px;text-align:center;color:var(--text3);">
        <div style="font-size:40px;margin-bottom:10px;opacity:.4;">📭</div>
        <div style="font-size:14px;font-weight:600;color:var(--text2);margin-bottom:4px;">Aucune campagne trouvée</div>
        <div style="font-size:12px;">Modifiez vos filtres pour afficher plus de résultats</div>
    </td>
</tr>
@endforelse
