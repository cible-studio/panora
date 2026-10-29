{{-- Une ligne « Autres interlocuteurs » du formulaire Nouveau client (2026-10-29). $i : index, $l : valeurs (old). --}}
@php $champ = 'width:100%;box-sizing:border-box;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;padding:9px 12px;color:var(--text);font-size:13px;outline:none;'; @endphp
<div style="display:grid;grid-template-columns:1.2fr 1fr 1.2fr 1fr auto;gap:8px;align-items:center;">
    <input type="text" name="interlocuteurs[{{ $i }}][name]" value="{{ $l['name'] ?? '' }}" placeholder="Nom et prénom" maxlength="120" style="{{ $champ }}">
    <input type="text" name="interlocuteurs[{{ $i }}][position]" value="{{ $l['position'] ?? '' }}" placeholder="Fonction" maxlength="100" style="{{ $champ }}">
    <input type="email" name="interlocuteurs[{{ $i }}][email]" value="{{ $l['email'] ?? '' }}" placeholder="E-mail" maxlength="150" style="{{ $champ }}">
    <input type="tel" name="interlocuteurs[{{ $i }}][phone]" value="{{ $l['phone'] ?? '' }}" placeholder="Téléphone" maxlength="30" style="{{ $champ }}">
    <button type="button" onclick="this.parentElement.remove()" title="Retirer cette ligne" aria-label="Retirer cette ligne"
            style="background:none;border:0;cursor:pointer;color:var(--text3);font-size:16px;padding:0 4px;">×</button>
</div>
