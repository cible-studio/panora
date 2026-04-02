<?php
namespace App\Console\Commands;
 
use App\Services\PropositionService;
use Illuminate\Console\Command;
 
class ExpirePropositions extends Command
{
    protected $signature   = 'propositions:expire';
    protected $description = 'Expire les propositions non confirmées après leur délai';
 
    public function handle(PropositionService $service): int
    {
        $this->info('Vérification des propositions expirées...');
 
        $count = $service->expireEnBatch();
 
        $this->info("✅ {$count} proposition(s) expirée(s) et annulées.");
        return self::SUCCESS;
    }
}
 