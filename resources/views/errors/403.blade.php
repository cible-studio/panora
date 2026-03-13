<x-admin-layout title="Accès refusé">
  <div style="text-align:center;padding:80px 20px;">
    <div style="font-size:64px;margin-bottom:16px;">🔒</div>
    <h1 style="font-size:24px;font-weight:700;color:var(--red);margin-bottom:8px;">
      Accès refusé
    </h1>
    <p style="color:var(--text2);font-size:14px;margin-bottom:24px;">
      {{ $exception->getMessage() ?: "Vous n'avez pas les droits pour effectuer cette action." }}
    </p>
    <a href="{{ url()->previous() }}" class="btn btn-ghost">← Retour</a>
  </div>
</x-admin-layout>