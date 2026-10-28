<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EnvoiContactsGmail;
use Illuminate\Http\Request;

/**
 * Journal des envois du bouton « Envoyer à Panora » du Google Sheets
 * (2026-10-28) : qui, quand, combien, et le résultat de chaque ligne.
 */
class EnvoisContactsGmailController extends Controller
{
    public function index(Request $request)
    {
        $envois = EnvoiContactsGmail::query()
            ->when($request->input('mode') === 'envois', fn ($q) => $q->where('simulation', false))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.clients.envois-gmail', [
            'envois'     => $envois,
            'mode'       => $request->input('mode'),
            'configure'  => (string) config('contacts.gmail.token') !== '',
            'autorises'  => config('contacts.gmail.autorises'),
        ]);
    }
}
