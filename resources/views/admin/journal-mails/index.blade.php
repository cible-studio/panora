<x-admin-layout title="Journal des mails">

@php
    $filtre = fn (string $nom) => request($nom);
    $actif  = collect(['q', 'statut', 'canal', 'du', 'au'])->contains(fn ($n) => filled(request($n)));
    $champ  = 'background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:8px 10px;color:var(--text);font-size:13px;';
@endphp

<div style="max-width:1200px;margin:0 auto">

    {{-- En-tête --}}
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px">
        <div style="display:flex;align-items:center;gap:8px">
            <div style="font-size:18px;font-weight:800;color:var(--text)">Journal des mails</div>
            <span title="Chaque mail parti de Panora (serveur de mail ou Brevo) et chaque envoi échoué, avec ses destinataires exacts. Les envois de la diffusion des disponibilités ont leur propre journal (Diffusion dispos)."
                  style="cursor:help;color:var(--text3)">ⓘ</span>
        </div>
        <div style="display:flex;gap:8px;font-size:12px;font-weight:600">
            <span style="padding:4px 10px;border-radius:20px;background:rgba(34,197,94,.12);color:#16a34a">{{ number_format($resume['envoyes'], 0, ',', ' ') }} envoyés · 30 j</span>
            <a href="{{ route('admin.journal-mails.index', ['statut' => 'echec']) }}" style="text-decoration:none;padding:4px 10px;border-radius:20px;{{ $resume['echecs'] ? 'background:rgba(239,68,68,.12);color:#ef4444' : 'background:var(--surface3);color:var(--text3)' }}">{{ $resume['echecs'] }} échec{{ $resume['echecs'] > 1 ? 's' : '' }} · 30 j</a>
        </div>
    </div>

    {{-- Filtres --}}
    <form method="GET" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr auto;gap:8px;margin-bottom:12px;padding:12px;background:var(--surface);border:1px solid var(--border);border-radius:12px;align-items:center">
        <input type="text" name="q" value="{{ $filtre('q') }}" placeholder="🔍 Adresse ou objet…" style="{{ $champ }}">
        <select name="statut" style="{{ $champ }}" onchange="this.form.submit()">
            <option value="">Tous statuts</option>
            <option value="envoye" @selected($filtre('statut') === 'envoye')>Envoyés</option>
            <option value="echec" @selected($filtre('statut') === 'echec')>Échecs</option>
        </select>
        <select name="canal" style="{{ $champ }}" onchange="this.form.submit()">
            <option value="">Tous canaux</option>
            <option value="serveur" @selected($filtre('canal') === 'serveur')>Serveur Panora</option>
            <option value="brevo" @selected($filtre('canal') === 'brevo')>Brevo</option>
        </select>
        <input type="date" name="du" value="{{ $filtre('du') }}" title="Du" style="{{ $champ }}" onchange="this.form.submit()">
        <input type="date" name="au" value="{{ $filtre('au') }}" title="Au" style="{{ $champ }}" onchange="this.form.submit()">
        <div style="display:flex;gap:6px">
            <button type="submit" class="btn btn-ghost btn-sm">Filtrer</button>
            @if($actif)<a href="{{ route('admin.journal-mails.index') }}" class="btn btn-ghost btn-sm" title="Effacer les filtres">↻</a>@endif
        </div>
    </form>

    {{-- Liste --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;overflow:hidden">
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px">
                <thead>
                    <tr style="background:var(--surface2);color:var(--text3);text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.4px">
                        <th style="padding:9px 12px;font-weight:700">Date</th>
                        <th style="padding:9px 12px;font-weight:700">Mail</th>
                        <th style="padding:9px 12px;font-weight:700">Destinataires</th>
                        <th style="padding:9px 12px;font-weight:700">Dossier</th>
                        <th style="padding:9px 12px;font-weight:700">Statut</th>
                        <th style="padding:9px 12px;font-weight:700">Par</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($mails as $m)
                        <tr style="border-top:1px solid var(--border);vertical-align:top">
                            <td style="padding:9px 12px;white-space:nowrap;color:var(--text2)">
                                {{ $m->envoye_le?->format('d/m/Y') }}<div style="color:var(--text3);font-size:11.5px">{{ $m->envoye_le?->format('H:i') }}</div>
                            </td>
                            <td style="padding:9px 12px;max-width:320px">
                                <div style="font-weight:600;color:var(--text)">{{ $m->sujet ?: '—' }}</div>
                                <div style="color:var(--text3);font-size:11.5px">{{ $m->libelleType() }} · {{ $m->canal === 'brevo' ? 'Brevo' : 'Serveur Panora' }}</div>
                            </td>
                            <td style="padding:9px 12px;max-width:300px;word-break:break-word;color:var(--text)">
                                {{ $m->destinataires ?: '—' }}
                                @if($m->copie)<div style="color:var(--text3);font-size:11.5px">Copie : {{ $m->copie }}</div>@endif
                            </td>
                            <td style="padding:9px 12px;white-space:nowrap">
                                @if($m->dossier_type)
                                    @php $lien = $m->lienDossier(); $libelle = (\App\Models\JournalMail::DOSSIERS[$m->dossier_type][0] ?? ucfirst($m->dossier_type)) . ' #' . $m->dossier_id; @endphp
                                    @if($lien)<a href="{{ $lien }}" style="color:var(--accent);text-decoration:none">{{ $libelle }}</a>@else<span style="color:var(--text2)">{{ $libelle }}</span>@endif
                                @else
                                    <span style="color:var(--text3)">—</span>
                                @endif
                            </td>
                            <td style="padding:9px 12px;white-space:nowrap">
                                @if($m->statut === \App\Models\JournalMail::STATUT_ECHEC)
                                    <span title="{{ $m->erreur }}" style="cursor:help;font-size:11px;font-weight:700;padding:2px 8px;border-radius:9px;background:rgba(239,68,68,.12);color:#ef4444">Échec</span>
                                @else
                                    <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:9px;background:rgba(34,197,94,.12);color:#16a34a">Envoyé</span>
                                @endif
                            </td>
                            <td style="padding:9px 12px;color:var(--text2);white-space:nowrap">{{ $m->user?->name ?? 'Automatique' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="padding:48px;text-align:center;color:var(--text3)">
                            {{ $actif ? 'Aucun mail ne correspond à ces filtres.' : 'Aucun mail noté pour l\'instant : le journal se remplit à partir de maintenant.' }}
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($mails->hasPages())<div style="padding:12px 16px">{{ $mails->links() }}</div>@endif
    </div>
</div>

</x-admin-layout>
