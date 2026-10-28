<x-admin-layout title="Envois du Google Sheets">

<x-slot:topbarLeft>
    <a href="{{ route('admin.clients.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour aux clients
    </a>
</x-slot:topbarLeft>

@php
    $couleurs = [
        'cree'     => ['rgba(34,197,94,.12)', '#16a34a'],
        'complete' => ['rgba(59,130,246,.12)', '#3b82f6'],
        'a_jour'   => ['var(--surface3)', 'var(--text3)'],
        'refuse'   => ['rgba(239,68,68,.12)', '#ef4444'],
    ];
    $libelles = \App\Models\EnvoiContactsGmail::RESULTATS;
@endphp

<div style="max-width:1100px;margin:0 auto">

    {{-- ── En-tête : état de la porte d'entrée ───────────────────── --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:10px">
            <div style="font-size:17px;font-weight:800;color:var(--text)">Envois du Google Sheets</div>
            <span title="Chaque clic sur « Envoyer à Panora » (ou « Vérifier ») dans le Sheets est noté ici, avec le résultat de chaque ligne. Une adresse connue n'est jamais dupliquée ; seuls les champs vides sont complétés."
                  style="cursor:help;color:var(--text3);font-size:14px">ⓘ</span>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            @if($configure)
                <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;background:rgba(34,197,94,.12);color:#16a34a">Porte ouverte</span>
            @else
                <span title="Renseigner GMAIL_SYNC_TOKEN dans Coolify"
                      style="cursor:help;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;background:rgba(239,68,68,.12);color:#ef4444">Porte fermée</span>
            @endif
            <span title="{{ $autorises ? implode(', ', $autorises) : 'Personne : renseigner GMAIL_SYNC_AUTORISES dans Coolify' }}"
                  style="cursor:help;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;background:var(--surface3);color:var(--text2)">
                {{ count($autorises) }} personne{{ count($autorises) > 1 ? 's' : '' }} autorisée{{ count($autorises) > 1 ? 's' : '' }}
            </span>
            <a href="{{ route('admin.clients.envois-gmail', $mode === 'envois' ? [] : ['mode' => 'envois']) }}" class="btn btn-ghost btn-sm">
                {{ $mode === 'envois' ? 'Voir aussi les vérifications' : 'Envois seulement' }}
            </a>
        </div>
    </div>

    @if($envois->isEmpty())
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:48px 24px;text-align:center;color:var(--text3);font-size:13px">
            Aucun envoi pour l'instant.
        </div>
    @else
        <div style="display:flex;flex-direction:column;gap:8px">
            @foreach($envois as $envoi)
                <details style="background:var(--surface);border:1px solid var(--border);border-radius:12px;overflow:hidden">
                    <summary style="cursor:pointer;padding:12px 16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;list-style:none">
                        <span style="font-size:13px;font-weight:700;color:var(--text);min-width:120px">{{ $envoi->created_at->format('d/m/Y H:i') }}</span>
                        @if($envoi->simulation)
                            <span title="Vérification : rien n'a été écrit dans Panora" style="cursor:help;font-size:10px;font-weight:700;padding:2px 8px;border-radius:9px;background:var(--surface3);color:var(--text2)">VÉRIFICATION</span>
                        @else
                            <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:9px;background:rgba(232,160,32,.15);color:var(--accent)">ENVOI</span>
                        @endif
                        <span style="font-size:12px;color:var(--text2);flex:1;min-width:160px">{{ $envoi->envoye_par }}</span>
                        <span style="display:flex;gap:6px;flex-wrap:wrap;font-size:11px;font-weight:600">
                            @foreach(['cree' => $envoi->crees, 'complete' => $envoi->completes, 'a_jour' => $envoi->a_jour, 'refuse' => $envoi->refuses] as $cle => $n)
                                @if($n > 0)
                                    <span style="padding:2px 8px;border-radius:9px;background:{{ $couleurs[$cle][0] }};color:{{ $couleurs[$cle][1] }}">{{ $libelles[$cle] }} {{ $n }}</span>
                                @endif
                            @endforeach
                            @if($envoi->entreprises_creees > 0)
                                <span style="padding:2px 8px;border-radius:9px;background:var(--surface3);color:var(--text2)">{{ $envoi->entreprises_creees }} entreprise{{ $envoi->entreprises_creees > 1 ? 's' : '' }} créée{{ $envoi->entreprises_creees > 1 ? 's' : '' }}</span>
                            @endif
                        </span>
                    </summary>
                    <div style="border-top:1px solid var(--border);overflow-x:auto">
                        <table style="width:100%;border-collapse:collapse;font-size:12px">
                            <thead>
                                <tr style="background:var(--surface2);color:var(--text3);text-align:left">
                                    <th style="padding:7px 12px;font-weight:600">Ligne</th>
                                    <th style="padding:7px 12px;font-weight:600">Adresse</th>
                                    <th style="padding:7px 12px;font-weight:600">Résultat</th>
                                    <th style="padding:7px 12px;font-weight:600">Détail</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($envoi->detail ?? [] as $l)
                                    @php $c = $couleurs[$l['resultat'] ?? ''] ?? $couleurs['a_jour']; @endphp
                                    <tr style="border-top:1px solid var(--border)">
                                        <td style="padding:6px 12px;color:var(--text3)">{{ $l['ligne'] ?? '' }}</td>
                                        <td style="padding:6px 12px;color:var(--text)">{{ $l['email'] ?? '' }}</td>
                                        <td style="padding:6px 12px">
                                            <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:9px;background:{{ $c[0] }};color:{{ $c[1] }}">{{ $libelles[$l['resultat'] ?? ''] ?? '—' }}</span>
                                        </td>
                                        <td style="padding:6px 12px;color:var(--text2)">
                                            @if(!$envoi->simulation && !empty($l['client_id']))
                                                <a href="{{ route('admin.clients.show', $l['client_id']) }}" style="color:var(--accent);text-decoration:none">{{ $l['message'] ?? '' }}</a>
                                            @else
                                                {{ $l['message'] ?? '' }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endforeach
        </div>

        <div style="margin-top:14px">{{ $envois->links() }}</div>
    @endif
</div>

</x-admin-layout>
