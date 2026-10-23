<?php
namespace App\Console\Commands;
use App\Models\Incident; use Illuminate\Console\Command;
class ExpireIncidents extends Command { protected $signature='veiligbuiten:expire-incidents'; protected $description='Markeer verlopen incidenten als verlopen.'; public function handle(): int { $count=Incident::where('expires_at','<=',now())->whereNull('resolved_at')->update(['resolved_at'=>now(),'status'=>'verlopen','visibility'=>'archived','archived_at'=>now()]);$this->info("{$count} incidenten verlopen.");return self::SUCCESS; } }
