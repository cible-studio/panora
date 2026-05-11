{{--
    Une entrée d'interlocuteur sur la fiche client. Réutilisée :
    - au rendu initial (foreach contacts)
    - en réponse AJAX à la création (le JS injecte ce HTML dans la liste)

    Toutes les actions sont des boutons inline qui parlent à
    ClientContacts.* (cf. show.blade.php).
--}}
<div class="contact-item" data-contact-id="{{ $contact->id }}"
     style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 12px;display:flex;flex-direction:column;gap:4px;{{ $contact->is_primary ? 'border-left:3px solid var(--accent);' : '' }}">

    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;min-width:0;">
            <span style="font-weight:700;font-size:13px;color:var(--text);">{{ $contact->name }}</span>
            @if($contact->is_primary)
                <span style="font-size:9px;font-weight:700;padding:1px 7px;border-radius:9px;background:rgba(232,160,32,.15);color:var(--accent);letter-spacing:.4px;">PRINCIPAL</span>
            @endif
        </div>
        <div style="display:flex;gap:2px;flex-shrink:0;">
            @if(!$contact->is_primary)
                <button type="button" onclick="ClientContacts.setPrimary({{ $contact->id }})" class="btn-icon" title="Définir comme contact principal" style="padding:3px 6px;font-size:12px;color:var(--text3);background:none;border:none;cursor:pointer;">⭐</button>
            @endif
            <button type="button" onclick="ClientContacts.openEdit({{ $contact->id }})" class="btn-icon" title="Modifier" style="padding:3px 6px;font-size:12px;color:var(--text3);background:none;border:none;cursor:pointer;">✏️</button>
            <button type="button" onclick="ClientContacts.remove({{ $contact->id }}, '{{ addslashes($contact->name) }}')" class="btn-icon" title="Supprimer" style="padding:3px 6px;font-size:12px;color:#ef4444;background:none;border:none;cursor:pointer;">🗑</button>
        </div>
    </div>

    <div style="font-size:11px;color:var(--text3);display:flex;flex-wrap:wrap;gap:8px;">
        <span>{{ $contact->role_label }}</span>
        @if($contact->position) · <span>{{ $contact->position }}</span> @endif
    </div>

    @if($contact->email || $contact->phone)
    <div style="font-size:11px;color:var(--text2);display:flex;flex-wrap:wrap;gap:10px;margin-top:2px;">
        @if($contact->email) <span>📧 <a href="mailto:{{ $contact->email }}" style="color:var(--text2);text-decoration:none;">{{ $contact->email }}</a></span> @endif
        @if($contact->phone) <span>📞 {{ $contact->phone }}</span> @endif
    </div>
    @endif
</div>
