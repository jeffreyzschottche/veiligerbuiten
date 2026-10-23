<?php

namespace Tests\Unit;

use App\Models\Incident;
use App\Support\DangerScale;
use Tests\TestCase;

class DangerScaleTest extends TestCase
{
    private function incident(int $confirmations, string $source = 'user', int $severity = 4): Incident
    {
        return new Incident(['confirmations_count' => $confirmations, 'source' => $source, 'severity' => $severity]);
    }

    public function test_reach_grows_with_confirmations_but_stays_bounded(): void
    {
        $one = DangerScale::alertRadius($this->incident(1));
        $five = DangerScale::alertRadius($this->incident(5));
        $many = DangerScale::alertRadius($this->incident(500));

        $this->assertSame(1500, $one);
        $this->assertGreaterThan($one, $five);
        $this->assertSame(4500, $many); // max 3× het basisbereik
    }

    public function test_dot_scale_is_modest(): void
    {
        $this->assertSame(1.0, DangerScale::dotScale($this->incident(1)));
        $this->assertSame(2.0, DangerScale::dotScale($this->incident(1000)));
    }

    public function test_levels_follow_severity(): void
    {
        $this->assertSame('danger', DangerScale::level($this->incident(1, severity: 5)));
        $this->assertSame('warn', DangerScale::level($this->incident(1, severity: 3)));
        $this->assertSame('info', DangerScale::level($this->incident(1, severity: 2)));
        $this->assertSame(6, DangerScale::weight($this->incident(1, 'ndw')));
    }
}
