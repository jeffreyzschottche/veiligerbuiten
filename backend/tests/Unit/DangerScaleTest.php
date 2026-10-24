<?php

namespace Tests\Unit;

use App\Models\Incident;
use App\Support\DangerScale;
use Tests\TestCase;

class DangerScaleTest extends TestCase
{
    private function incident(int $confirmations, string $source = 'user', int $severity = 4, array $extra = []): Incident
    {
        return new Incident(['confirmations_count' => $confirmations, 'source' => $source, 'severity' => $severity, 'category' => 'brand', 'last_confirmed_at' => now(), ...$extra]);
    }

    public function test_reach_depends_on_category_grows_with_confirmations_and_stays_bounded(): void
    {
        $one = DangerScale::alertRadius($this->incident(2));
        $five = DangerScale::alertRadius($this->incident(5));
        $many = DangerScale::alertRadius($this->incident(500));
        $fight = DangerScale::alertRadius($this->incident(2, extra: ['category' => 'geweld']));

        $this->assertSame(1292, $one); // 1000 × (0,5 + 0,5·log2(1 + 2))
        $this->assertGreaterThan($one, $five);
        $this->assertSame(3000, $many); // max 3× het basisbereik
        $this->assertLessThan($one, $fight, 'a fight reaches less far than a fire');
    }

    public function test_reach_shrinks_as_a_report_gets_old(): void
    {
        $fresh = DangerScale::alertRadius($this->incident(2, 'p2000_zwaailicht'));
        $old = DangerScale::alertRadius($this->incident(2, 'p2000_zwaailicht', extra: ['last_confirmed_at' => now()->subMinutes(60)]));

        $this->assertLessThan($fresh, $old);
    }

    public function test_dot_scale_is_modest(): void
    {
        $this->assertSame(1.0, DangerScale::dotScale($this->incident(1)));
        $this->assertSame(2.0, DangerScale::dotScale($this->incident(1000)));
    }

    public function test_levels_follow_severity(): void
    {
        $this->assertSame('danger', DangerScale::level($this->incident(1, 'ndw', severity: 5)));
        $this->assertSame('warn', DangerScale::level($this->incident(1, severity: 3)));
        $this->assertSame('info', DangerScale::level($this->incident(1, severity: 2)));
        $this->assertSame(6, DangerScale::weight($this->incident(1, 'ndw')));
    }

    public function test_a_single_unconfirmed_report_is_never_danger_right_away(): void
    {
        $this->assertSame('warn', DangerScale::level($this->incident(1, severity: 5)));
        $this->assertSame('danger', DangerScale::level($this->incident(2, severity: 5)));
        $this->assertSame('danger', DangerScale::level($this->incident(1, severity: 5, extra: ['risk_flags' => ['official_nearby' => true]])));
    }

    public function test_doubt_age_and_clusters_move_the_level(): void
    {
        $this->assertSame('warn', DangerScale::level($this->incident(2, severity: 5, extra: ['resolved_count' => 2])), 'as many "gone" votes as confirmations');
        $this->assertSame('warn', DangerScale::level($this->incident(2, 'p2000_zwaailicht', 5, ['last_confirmed_at' => now()->subHours(3)])), 'stale');
        $this->assertSame('danger', DangerScale::level($this->incident(2, 'p2000_zwaailicht', 3, ['risk_flags' => ['cluster' => 2]])), 'several signals in one spot');
    }

    public function test_confidence_reflects_confirmations_and_doubt(): void
    {
        $this->assertLessThan(DangerScale::confidence($this->incident(3)), DangerScale::confidence($this->incident(1)));
        $this->assertLessThan(DangerScale::confidence($this->incident(3)), DangerScale::confidence($this->incident(3, extra: ['resolved_count' => 2])));
        $this->assertSame(1.0, DangerScale::confidence($this->incident(0, 'nl_alert')));
    }
}
