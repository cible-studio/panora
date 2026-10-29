<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JournalMail;
use Illuminate\Http\Request;

/**
 * Journal des mails (2026-10-29) : qui a reçu quoi, quand — chaque mail
 * parti de Panora (serveur de mail ou Brevo) et chaque envoi échoué.
 */
class JournalMailsController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));

        $mails = JournalMail::query()
            ->with('user:id,name')
            ->when($q !== '', fn ($r) => $r->where(fn ($w) => $w
                ->where('destinataires', 'like', "%{$q}%")
                ->orWhere('copie', 'like', "%{$q}%")
                ->orWhere('sujet', 'like', "%{$q}%")))
            ->when(in_array($request->input('statut'), [JournalMail::STATUT_ENVOYE, JournalMail::STATUT_ECHEC], true),
                fn ($r) => $r->where('statut', $request->input('statut')))
            ->when(in_array($request->input('canal'), ['serveur', 'brevo'], true),
                fn ($r) => $r->where('canal', $request->input('canal')))
            ->when($request->filled('du'), fn ($r) => $r->whereDate('envoye_le', '>=', $request->input('du')))
            ->when($request->filled('au'), fn ($r) => $r->whereDate('envoye_le', '<=', $request->input('au')))
            ->latest('envoye_le')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $depuis = now()->subDays(30);
        $resume = [
            'envoyes' => JournalMail::where('statut', JournalMail::STATUT_ENVOYE)->where('envoye_le', '>=', $depuis)->count(),
            'echecs'  => JournalMail::where('statut', JournalMail::STATUT_ECHEC)->where('envoye_le', '>=', $depuis)->count(),
        ];

        return view('admin.journal-mails.index', compact('mails', 'resume'));
    }
}
