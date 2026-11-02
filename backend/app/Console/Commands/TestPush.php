<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PushDelivery;
use Illuminate\Console\Command;

/** Stuurt een testmelding naar alle toestellen van één account en laat zien wat Apple/Firebase terugzegt. */
class TestPush extends Command
{
    protected $signature = 'veiligbuiten:test-push {email : E-mailadres van het account} {--level=warn : danger (met geluid, doorbreekt focus), warn of info (stil)}';

    protected $description = 'Stuur een testpush naar de toestellen van een account.';

    public function handle(PushDelivery $push): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        $level = in_array($this->option('level'), ['danger', 'warn', 'info'], true) ? $this->option('level') : 'warn';
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();
        if (! $user) {
            $this->error("Geen account met {$email}.");
            $like = User::query()->where('email', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], strtok($email, '@')).'%')->limit(5)->pluck('email');
            if ($like->isNotEmpty()) {
                $this->line('Bedoel je: '.$like->implode(', '));
            }

            return self::FAILURE;
        }

        $installations = $user->installations()->orderByDesc('last_seen_at')->get();
        if ($installations->isEmpty()) {
            $this->warn("{$user->email} heeft nog geen toestel gekoppeld. Open de app ingelogd op je telefoon en probeer het opnieuw.");

            return self::FAILURE;
        }

        $rows = [];
        foreach ($installations as $installation) {
            $result = $push->sendTest($installation, $level);
            $rows[] = [
                $installation->id,
                $installation->push_platform ?? '-',
                $installation->push_token ? substr($installation->push_token, 0, 10).'…' : '-',
                $installation->last_seen_at?->diffForHumans() ?? '-',
                $result['status'],
                $result['detail'],
            ];
        }
        $this->table(['Toestel', 'Platform', 'Token', 'Laatst gezien', 'Resultaat', 'Toelichting'], $rows);

        return collect($rows)->contains(fn ($row) => $row[4] === 'sent') ? self::SUCCESS : self::FAILURE;
    }
}
