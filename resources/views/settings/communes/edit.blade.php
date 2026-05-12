<x-admin-layout>
<x-slot name="title">Modifier Commune</x-slot>

<div style="max-width:760px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">✏️ Modifier — {{ $commune->name }}</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.settings.communes.update', $commune) }}">
                @csrf
                @method('PUT')

                <div class="form-2col">
                    <div class="mfg">
                        <label>Nom de la commune *</label>
                        <input type="text" name="name"
                               value="{{ old('name', $commune->name) }}"
                               class="{{ $errors->has('name') ? 'error' : '' }}">
                        @error('name')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mfg">
                        <label>Ville</label>
                        <input type="text" name="city"
                               value="{{ old('city', $commune->city) }}"
                               placeholder="Ex: Abidjan">
                    </div>
                </div>

                <div class="mfg">
                    <label>Région</label>
                    <input type="text" name="region"
                           value="{{ old('region', $commune->region) }}"
                           placeholder="Ex: Abidjan / Intérieur Pays">
                </div>

                <div class="section-label">Taxes communales (FCFA / m² / mois)</div>

                <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:12px;color:var(--text2);">
                    💡 Toute modification d'un tarif est <strong>historisée automatiquement</strong>.
                    Les rapports rétroactifs (calculs sur mois passés) continueront d'utiliser les anciens tarifs —
                    seuls les calculs <strong>à partir d'aujourd'hui</strong> appliqueront les nouvelles valeurs.
                </div>

                <div class="form-2col" style="grid-template-columns:repeat(3, 1fr);">
                    <div class="mfg">
                        <label>Tarif ODP</label>
                        <input type="number" name="odp_rate"
                               value="{{ old('odp_rate', $commune->odp_rate) }}"
                               step="0.01" min="0">
                        <div style="font-size:10px;color:var(--text3);margin-top:2px;">Tous les panneaux</div>
                    </div>

                    <div class="mfg">
                        <label>Tarif TM</label>
                        <input type="number" name="tm_rate"
                               value="{{ old('tm_rate', $commune->tm_rate) }}"
                               step="0.01" min="0">
                        <div style="font-size:10px;color:var(--text3);margin-top:2px;">Avec campagne active uniquement</div>
                    </div>

                    <div class="mfg">
                        <label>Tarif DB</label>
                        <input type="number" name="db_rate"
                               value="{{ old('db_rate', $commune->db_rate) }}"
                               step="0.01" min="0">
                        <div style="font-size:10px;color:var(--text3);margin-top:2px;">Tous les panneaux</div>
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:8px;">
                    <button type="submit" class="btn btn-primary">
                        💾 Enregistrer
                    </button>
                    <a href="{{ route('admin.settings.communes.index') }}"
                       class="btn btn-ghost">
                        Annuler
                    </a>
                </div>

            </form>
        </div>
    </div>

    {{-- ═══ HISTORIQUE TARIFAIRE ═══ --}}
    @if($commune->rateHistory && $commune->rateHistory->count() > 0)
    <div class="card" style="margin-top:16px;">
        <div class="card-header">
            <div class="card-title">📜 Historique tarifaire — {{ $commune->rateHistory->count() }} entrée(s)</div>
        </div>
        <div class="card-body">
            <div style="font-size:11px;color:var(--text3);margin-bottom:12px;">
                Chaque ligne représente les tarifs en vigueur pendant une période donnée.
                Utilisé par les rapports rétroactifs pour garantir la cohérence des montants calculés.
            </div>
            <div class="table-wrap" style="max-height:380px;overflow:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Du</th>
                            <th>Au</th>
                            <th style="text-align:right;">ODP</th>
                            <th style="text-align:right;">TM</th>
                            <th style="text-align:right;">DB</th>
                            <th>Modifié par</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($commune->rateHistory as $h)
                        @php
                            $isCurrent = $h->effective_to === null;
                        @endphp
                        <tr style="{{ $isCurrent ? 'background:rgba(34,197,94,.05);' : '' }}">
                            <td style="font-size:12px;">
                                {{ $h->effective_from?->format('d/m/Y') }}
                                @if($isCurrent)
                                    <span class="badge badge-green" style="margin-left:6px;">en vigueur</span>
                                @endif
                            </td>
                            <td style="font-size:12px;color:var(--text2);">
                                {{ $h->effective_to ? $h->effective_to->format('d/m/Y') : '—' }}
                            </td>
                            <td style="text-align:right;font-family:monospace;">{{ number_format($h->odp_rate, 0, ',', ' ') }}</td>
                            <td style="text-align:right;font-family:monospace;">{{ number_format($h->tm_rate, 0, ',', ' ') }}</td>
                            <td style="text-align:right;font-family:monospace;">{{ number_format($h->db_rate, 0, ',', ' ') }}</td>
                            <td style="font-size:11px;color:var(--text3);">{{ $h->createdBy?->name ?? '—' }}</td>
                            <td style="font-size:11px;color:var(--text3);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $h->notes }}">{{ $h->notes }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>

</x-admin-layout>

