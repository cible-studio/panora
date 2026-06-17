{{--
    Modale d'édition de motif a posteriori — Module 3 SLA enrichi.

    Variables attendues :
      $action = PoseTaskAction problem_reported à amender

    Logique :
      - Affiche le motif d'origine en lecture seule.
      - Select avec les 9 motifs (sauf le motif courant — service::amend()
        rejette si identique).
      - Textarea justification obligatoire (>= 10 chars).
      - POST → /admin/sla/retards/{action}/motif (PUT)

    Anti-écrasement : l'original n'est JAMAIS touché. Une nouvelle ligne
    motif_modified est créée — préservation audit trail (Décision G).
--}}
@php
    $effective = $action->effectiveMotif();
    $origin    = $action->originMotif();
@endphp

<div id="modal-edit-motif-{{ $action->id }}" class="sla-modal-overlay" onclick="if(event.target===this)slaCloseModal({{ $action->id }})" style="display:none">
    <div class="sla-modal">
        <div class="sla-modal-head">
            <div style="display:flex;align-items:center;gap:8px">
                <span style="font-size:20px">✎</span>
                <div>
                    <div style="font-weight:800;font-size:15px;color:var(--text)">Modifier le motif</div>
                    <div style="font-size:11px;color:var(--text3)">L'historique d'origine est préservé.</div>
                </div>
            </div>
            <button type="button" onclick="slaCloseModal({{ $action->id }})"
                    style="background:none;border:none;font-size:18px;cursor:pointer;color:var(--text3)">✕</button>
        </div>

        <form method="POST" action="{{ route('admin.sla.retards.motif.update', $action) }}" class="sla-modal-body">
            @csrf
            @method('PUT')

            {{-- Lecture seule : motif d'origine + motif courant si différent --}}
            <div style="background:var(--surface2);padding:10px 12px;border-radius:8px;margin-bottom:14px;font-size:12px">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span style="color:var(--text3)">Motif d'origine</span>
                    <span style="font-weight:700;color:{{ $origin?->color() ?? 'var(--text)' }}">{{ $origin?->icon() }} {{ $origin?->label() ?? '—' }}</span>
                </div>
                @if($effective && $effective !== $origin)
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;padding-top:6px;border-top:1px dashed var(--border)">
                        <span style="color:var(--text3)">Motif courant (déjà amendé)</span>
                        <span style="font-weight:700;color:{{ $effective->color() }}">{{ $effective->icon() }} {{ $effective->label() }}</span>
                    </div>
                @endif
            </div>

            <div class="fne-field">
                <label>Nouveau motif <span class="req">*</span></label>
                <select name="motif" required>
                    <option value="">— Choisir —</option>
                    @foreach(\App\Enums\DelayReason::cases() as $m)
                        <option value="{{ $m->value }}"
                                {{ ($effective && $effective === $m) ? 'disabled' : '' }}>
                            {{ $m->icon() }} {{ $m->label() }}{{ ($effective && $effective === $m) ? ' (motif courant)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="fne-field">
                <label>Pourquoi cette modification ? <span class="req">*</span> <span class="opt">— 10 caractères min, conservé en audit</span></label>
                <textarea name="reason_text" rows="3" required minlength="10" maxlength="500"
                          placeholder="Ex : tech a précisé verbalement que c'était météo, pas accès bloqué"></textarea>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:14px;padding-top:14px;border-top:1px solid var(--border)">
                <button type="button" class="btn btn-ghost" onclick="slaCloseModal({{ $action->id }})">Annuler</button>
                <button type="submit" class="btn btn-primary">✎ Enregistrer la modification</button>
            </div>
        </form>
    </div>
</div>
