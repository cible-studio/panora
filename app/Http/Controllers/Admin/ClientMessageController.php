<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientMessage;
use App\Services\AlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Interface admin pour les messages envoyés via le formulaire
 * « Contacter la régie » de l'espace client.
 *
 * Flow :
 *  - Le client envoie un message → ligne en BD + email + alerte.
 *  - Admin ouvre /admin/messages → liste filtrable.
 *  - Admin ouvre /admin/messages/{id} → marque lu automatiquement +
 *    bouton « Répondre » qui stocke la réponse et envoie un email
 *    au client (reply_to = mailbox équipe).
 */
class ClientMessageController extends Controller
{
    public function index(Request $request)
    {
        $query = ClientMessage::query()
            ->with(['client:id,name,company,email', 'reader:id,name', 'replier:id,name'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = '%' . $request->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('subject', 'like', $s)
                  ->orWhere('body', 'like', $s)
                  ->orWhere('from_name', 'like', $s)
                  ->orWhere('from_email', 'like', $s);
            });
        }

        $messages = $query->paginate(20)->withQueryString();

        $counts = [
            'all'         => ClientMessage::count(),
            'new'         => ClientMessage::where('status', 'new')->count(),
            'in_progress' => ClientMessage::where('status', 'in_progress')->count(),
            'replied'     => ClientMessage::where('status', 'replied')->count(),
            'archived'    => ClientMessage::where('status', 'archived')->count(),
        ];

        return view('admin.messages.index', compact('messages', 'counts'));
    }

    public function show(ClientMessage $message)
    {
        $message->load(['client', 'reader', 'replier']);

        // Marque automatique « lu » à la première ouverture — feedback
        // implicite : si je viens de cliquer sur l'alerte, je sais que
        // je l'ai vue, pas besoin de bouton dédié.
        if ($message->status === 'new') {
            $message->update([
                'status'  => 'in_progress',
                'read_at' => now(),
                'read_by' => Auth::id(),
            ]);
            // Le badge sidebar « N nouveaux messages » doit décrémenter.
            Cache::forget('admin.messages.new_count');
        }

        return view('admin.messages.show', compact('message'));
    }

    public function reply(Request $request, ClientMessage $message)
    {
        $data = $request->validate([
            'reply_body' => 'required|string|min:5|max:5000',
        ], [
            'reply_body.required' => 'La réponse est obligatoire.',
            'reply_body.min'      => 'La réponse doit faire au moins 5 caractères.',
        ]);

        // Envoi mail au client — best-effort, on persiste même en cas
        // d'échec d'envoi pour ne pas perdre la rédaction de l'opérateur.
        $mailSent = true;
        try {
            $admin   = Auth::user();
            $from    = config('mail.from.address');
            $body    = "Bonjour {$message->from_name},\n\n"
                     . $data['reply_body']
                     . "\n\n— L'équipe CIBLE CI"
                     . "\n(en réponse à votre message du " . $message->created_at->format('d/m/Y à H:i') . ' : ' . Str::limit($message->subject, 60) . ')';

            Mail::raw($body, function ($m) use ($message, $from) {
                $m->to($message->from_email, $message->from_name)
                  ->replyTo($from)
                  ->subject('Re: ' . $message->subject);
            });
        } catch (\Throwable $e) {
            $mailSent = false;
            Log::warning('client.message.reply_mail_failed', [
                'message_id' => $message->id, 'error' => $e->getMessage(),
            ]);
        }

        $message->update([
            'status'     => 'replied',
            'reply_body' => $data['reply_body'],
            'replied_at' => now(),
            'replied_by' => Auth::id(),
        ]);

        $flash = $mailSent
            ? 'Réponse envoyée à ' . $message->from_email . '.'
            : 'Réponse enregistrée mais l\'envoi email a échoué — à renvoyer manuellement.';

        return back()->with($mailSent ? 'success' : 'warning', $flash);
    }

    public function archive(ClientMessage $message)
    {
        $message->update(['status' => 'archived']);
        return back()->with('success', 'Message archivé.');
    }

    public function destroy(ClientMessage $message)
    {
        $message->delete();
        return redirect()->route('admin.messages.index')
                         ->with('success', 'Message supprimé.');
    }
}
