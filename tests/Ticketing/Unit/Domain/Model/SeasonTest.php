<?php

declare(strict_types=1);

namespace Tests\Ticketing\Unit\Domain\Model;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Src\Ticketing\Domain\Model\Season;

class SeasonTest extends TestCase
{
    public function test_it_exposes_the_data_it_was_built_with(): void
    {
        $season = new Season(
            1,
            '2026/27',
            new DateTimeImmutable('2026-09-01'),
            new DateTimeImmutable('2027-06-30'),
            previousSeasonId: 99,
            renewalStartDate: new DateTimeImmutable('2026-07-01'),
            renewalEndDate: new DateTimeImmutable('2026-07-31')
        );

        $this->assertSame(1, $season->id());
        $this->assertSame('2026/27', $season->name());
        $this->assertEquals(new DateTimeImmutable('2026-09-01'), $season->startDate());
        $this->assertEquals(new DateTimeImmutable('2027-06-30'), $season->endDate());
        $this->assertSame(99, $season->previousSeasonId());
        $this->assertEquals(new DateTimeImmutable('2026-07-01'), $season->renewalStartDate());
        $this->assertEquals(new DateTimeImmutable('2026-07-31'), $season->renewalEndDate());
    }

    public function test_it_cannot_end_before_it_starts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Season(
            1,
            '2026/27',
            new DateTimeImmutable('2027-06-30'),
            new DateTimeImmutable('2026-09-01')
        );
    }

    public function test_it_cannot_end_on_the_day_it_starts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Season(
            1,
            '2026/27',
            new DateTimeImmutable('2026-09-01'),
            new DateTimeImmutable('2026-09-01')
        );
    }

    public function test_its_renewal_window_cannot_end_before_it_opens(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Season(
            1,
            '2026/27',
            new DateTimeImmutable('2026-09-01'),
            new DateTimeImmutable('2027-06-30'),
            renewalStartDate: new DateTimeImmutable('2026-07-31'),
            renewalEndDate: new DateTimeImmutable('2026-07-01')
        );
    }

    public function test_it_is_inside_the_renewal_window_between_the_two_dates(): void
    {
        $season = $this->seasonWithRenewalWindow();

        $this->assertTrue($season->isRenewalWindow(new DateTimeImmutable('2026-07-15')));
    }

    public function test_the_renewal_window_includes_both_boundaries(): void
    {
        $season = $this->seasonWithRenewalWindow();

        $this->assertTrue($season->isRenewalWindow(new DateTimeImmutable('2026-07-01')));
        $this->assertTrue($season->isRenewalWindow(new DateTimeImmutable('2026-07-31')));
    }

    public function test_it_is_outside_the_renewal_window_before_and_after(): void
    {
        $season = $this->seasonWithRenewalWindow();

        $this->assertFalse($season->isRenewalWindow(new DateTimeImmutable('2026-06-30')));
        $this->assertFalse($season->isRenewalWindow(new DateTimeImmutable('2026-08-01')));
    }

    public function test_a_season_without_renewal_dates_is_never_in_a_renewal_window(): void
    {
        $season = new Season(
            1,
            '2026/27',
            new DateTimeImmutable('2026-09-01'),
            new DateTimeImmutable('2027-06-30')
        );

        $this->assertNull($season->renewalStartDate());
        $this->assertNull($season->renewalEndDate());
        $this->assertNull($season->previousSeasonId());
        $this->assertFalse($season->isRenewalWindow(new DateTimeImmutable('2026-07-15')));
    }

    private function seasonWithRenewalWindow(): Season
    {
        return new Season(
            1,
            '2026/27',
            new DateTimeImmutable('2026-09-01'),
            new DateTimeImmutable('2027-06-30'),
            renewalStartDate: new DateTimeImmutable('2026-07-01'),
            renewalEndDate: new DateTimeImmutable('2026-07-31')
        );
    }
}
