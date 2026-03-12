{{-- Modal Edit --}}
<div x-data="{ open: false, client: {} }"
     x-on:open-modal.window="if($event.detail?.name === 'edit-client') { client = $event.detail.data; open = true; }"
     x-show="open" class="modal-overlay" @click.self="open = false" style="display:none;">
  <div class="modal" @click.stop>
    <div class="modal-header">
      <span class="modal-title">Modifier le client</span>
      <button class="modal-close" @click="open = false">✕</button>
    </div>
    <form method="POST" :action="`/admin/clients/${client.id}`">
      @csrf @method('PUT')
      <div class="modal-body">
        <div class="mfg">
          <label>Nom de l'entreprise *</label>
          <input type="text" name="name" :value="client.name" required/>
        </div>
        <div class="form-2col">
          <div class="mfg">
            <label>Secteur</label>
            <input type="text" name="sector" :value="client.sector"/>
          </div>
          <div class="mfg">
            <label>Contact</label>
            <input type="text" name="contact_name" :value="client.contact_name"/>
          </div>
        </div>
        <div class="form-2col">
          <div class="mfg">
            <label>Email</label>
            <input type="email" name="email" :value="client.email"/>
          </div>
          <div class="mfg">
            <label>Téléphone</label>
            <input type="text" name="phone" :value="client.phone"/>
          </div>
        </div>
        <div class="mfg">
          <label>Adresse</label>
          <textarea name="address" x-text="client.address"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" @click="open = false">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal Delete --}}
<div x-data="{ open: false, client: {} }"
     x-on:open-modal.window="if($event.detail?.name === 'delete-client') { client = $event.detail.data; open = true; }"
     x-show="open" class="modal-overlay" @click.self="open = false" style="display:none;">
  <div class="modal" style="width:420px;" @click.stop>
    <div class="modal-header">
      <span class="modal-title" style="color:var(--red);">Supprimer le client</span>
      <button class="modal-close" @click="open = false">✕</button>
    </div>
    <div class="modal-body" style="text-align:center;padding:32px 22px;">
      <div style="font-size:40px;margin-bottom:12px;">🗑️</div>
      <p style="font-size:15px;font-weight:600;margin-bottom:8px;">
        Supprimer <span x-text="client.name" style="color:var(--accent);"></span> ?
      </p>
      <p style="font-size:13px;color:var(--text2);">
        Cette action est irréversible.
      </p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" @click="open = false">Annuler</button>
      <form method="POST" :action="`/admin/clients/${client.id}`" style="display:inline;">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-danger">Confirmer la suppression</button>
      </form>
    </div>
  </div>
</div>