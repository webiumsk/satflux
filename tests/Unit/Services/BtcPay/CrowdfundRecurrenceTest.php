<?php

namespace Tests\Unit\Services\BtcPay;

use App\Services\BtcPay\Apps\CrowdfundRecurrence;
use App\Services\BtcPay\Apps\CrowdfundUpdatePayloadBuilder;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrowdfundRecurrenceTest extends TestCase
{
    #[DataProvider('validIntervals')]
    public function test_it_preserves_numeric_intervals_and_maps_weeks(string $period, mixed $amount, string $expectedPeriod, int $expectedAmount): void
    {
        $config = (new CrowdfundRecurrence)->normalize([
            'resetEvery' => $period,
            'resetEveryAmount' => $amount,
            'startDate' => 1793491200,
            'title' => 'Keep this title',
        ]);

        $this->assertSame($expectedPeriod, $config['resetEvery']);
        $this->assertSame($expectedAmount, $config['resetEveryAmount']);
        $this->assertSame('Keep this title', $config['title']);
    }

    public static function validIntervals(): array
    {
        return [
            'hours' => ['Hour', 5, 'Hour', 5],
            'days as numeric string' => ['Day', '5', 'Day', 5],
            'weeks' => ['Week', 2, 'Day', 14],
            'months' => ['Month', 3, 'Month', 3],
            'years' => ['Year', 2, 'Year', 2],
            'zero disables' => ['Day', 0, 'Never', 0],
            'never disables' => ['Never', 5, 'Never', 0],
        ];
    }

    #[DataProvider('invalidIntervals')]
    public function test_invalid_intervals_fail_with_field_errors(array $config, string $field): void
    {
        try {
            (new CrowdfundRecurrence)->normalize($config);
            $this->fail('Expected invalid recurrence to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public static function invalidIntervals(): array
    {
        $valid = ['resetEvery' => 'Day', 'resetEveryAmount' => 2, 'startDate' => 1793491200];

        return [
            'unsupported period' => [array_replace($valid, ['resetEvery' => 'Fortnight']), 'config.resetEvery'],
            'boolean amount' => [array_replace($valid, ['resetEveryAmount' => true]), 'config.resetEveryAmount'],
            'fractional amount' => [array_replace($valid, ['resetEveryAmount' => 1.5]), 'config.resetEveryAmount'],
            'negative amount' => [array_replace($valid, ['resetEveryAmount' => -1]), 'config.resetEveryAmount'],
            'non-numeric amount' => [array_replace($valid, ['resetEveryAmount' => 'five']), 'config.resetEveryAmount'],
            'overflowing weeks' => [array_replace($valid, ['resetEvery' => 'Week', 'resetEveryAmount' => 2147483647]), 'config.resetEveryAmount'],
            'missing start' => [array_replace($valid, ['startDate' => null]), 'config.startDate'],
            'malformed start' => [array_replace($valid, ['startDate' => 'not-a-date']), 'config.startDate'],
            'invalid calendar date' => [array_replace($valid, ['startDate' => '2026-02-30T00:00:00Z']), 'config.startDate'],
            'next day outside range' => [array_replace($valid, ['startDate' => '9999-12-31T00:00:00Z']), 'config.resetEveryAmount'],
            'next month outside range' => [array_replace($valid, ['resetEvery' => 'Month', 'startDate' => '9999-12-01T00:00:00Z']), 'config.resetEveryAmount'],
            'next year outside range' => [array_replace($valid, ['resetEvery' => 'Year', 'resetEveryAmount' => 8000]), 'config.resetEveryAmount'],
            'missing active amount' => [['resetEvery' => 'Day', 'startDate' => 1793491200], 'config.resetEveryAmount'],
            'missing active period' => [['resetEveryAmount' => 5, 'startDate' => 1793491200], 'config.resetEvery'],
        ];
    }

    public function test_month_end_and_leap_day_match_host_calendar_bounds(): void
    {
        foreach (['2028-02-29T12:00:00Z', '2026-01-31T12:00:00Z'] as $start) {
            $config = (new CrowdfundRecurrence)->normalize([
                'resetEvery' => 'Month', 'resetEveryAmount' => 1, 'startDate' => $start,
            ]);
            $this->assertSame(1, $config['resetEveryAmount']);
            $this->assertSame(strtotime($start), $config['startDate']);
        }
    }

    public function test_update_payload_does_not_collapse_recurrence_after_normalizing(): void
    {
        $payload = (new CrowdfundUpdatePayloadBuilder)->build([
            'resetEvery' => 'Week', 'resetEveryAmount' => 2, 'startDate' => 1793491200,
            'id' => 'foreign-app', 'storeId' => 'foreign-store',
        ], 'owned-app', 'owned-store');

        $this->assertSame('Day', $payload['resetEvery']);
        $this->assertSame(14, $payload['resetEveryAmount']);
        $this->assertSame(1793491200, $payload['startDate']);
        $this->assertSame('owned-app', $payload['id']);
        $this->assertSame('owned-store', $payload['storeId']);
    }

    public function test_sparse_config_does_not_invent_or_disable_recurrence(): void
    {
        $config = ['title' => 'Only change the title'];
        $this->assertSame($config, (new CrowdfundRecurrence)->normalize($config));
        $payload = (new CrowdfundUpdatePayloadBuilder)->build($config, 'owned-app', 'owned-store');
        $this->assertArrayNotHasKey('resetEvery', $payload);
        $this->assertArrayNotHasKey('resetEveryAmount', $payload);
    }
}
