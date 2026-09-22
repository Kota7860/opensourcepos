<?php

namespace Tests\Libraries;

use App\Libraries\Reward_lib;
use CodeIgniter\Test\CIUnitTestCase;

class Reward_libTest extends CIUnitTestCase
{
    private function makeLib(array $overrides = []): Reward_lib
    {
        $settings = array_merge([
            'currency_decimals'                  => '2',
            'customer_reward_points_value'       => '1',
            'customer_reward_min_redeem_points'  => '0',
            'customer_reward_max_redeem_percent' => '0',
        ], $overrides);

        return new Reward_lib($settings);
    }

    public function testCalculatePointsEarnedMatchesLegacyFormula(): void
    {
        $lib = $this->makeLib();

        $this->assertSame(1.0, $lib->calculate_points_earned(100.0, 1.0));
        $this->assertSame(5.0, $lib->calculate_points_earned(100.0, 5.0));
        $this->assertSame(0.0, $lib->calculate_points_earned(100.0, 0.0));
        $this->assertSame(2.5, $lib->calculate_points_earned(50.0, 5.0));
    }

    public function testPointValueDefaultsToOne(): void
    {
        $this->assertSame('1', $this->makeLib()->point_value());
        $this->assertSame('1', $this->makeLib(['customer_reward_points_value' => ''])->point_value());
        $this->assertSame('0.05', $this->makeLib(['customer_reward_points_value' => '0.05'])->point_value());
    }

    public function testPointsToCurrencyUsesPointValueAndScale(): void
    {
        $this->assertSame('100.00', $this->makeLib()->points_to_currency(100.0));
        $this->assertSame('5.00', $this->makeLib(['customer_reward_points_value' => '0.05'])->points_to_currency(100.0));
    }

    public function testCanRedeemRespectsMinimumThreshold(): void
    {
        $lib = $this->makeLib(['customer_reward_min_redeem_points' => '50']);

        $this->assertFalse($lib->can_redeem(49.0));
        $this->assertTrue($lib->can_redeem(50.0));
        $this->assertTrue($lib->can_redeem(100.0));
    }

    public function testMaxRedeemableDefaultsToBalanceCappedBySaleTotal(): void
    {
        $lib = $this->makeLib();

        // Balance worth more than the sale: capped at the sale total.
        $this->assertSame('20.00', $lib->max_redeemable_amount(100.0, 20.0));
        // Balance worth less than the sale: capped at the balance value.
        $this->assertSame('10.00', $lib->max_redeemable_amount(10.0, 20.0));
    }

    public function testMaxRedeemableHonorsPercentCap(): void
    {
        $lib = $this->makeLib(['customer_reward_max_redeem_percent' => '50']);

        // 50% of a 20.00 sale = 10.00, even though the balance is worth 100.00.
        $this->assertSame('10.00', $lib->max_redeemable_amount(100.0, 20.0));
    }

    public function testMaxRedeemableHonorsMinimumThreshold(): void
    {
        $lib = $this->makeLib(['customer_reward_min_redeem_points' => '50']);

        // Below the threshold: nothing may be redeemed.
        $this->assertSame('0.00', $lib->max_redeemable_amount(49.0, 20.0));
        // At the threshold: redemption allowed, capped by the sale total.
        $this->assertSame('20.00', $lib->max_redeemable_amount(50.0, 20.0));
    }

    public function testMaxRedeemableReturnsZeroForNonPositiveSale(): void
    {
        $lib = $this->makeLib();

        $this->assertSame('0.00', $lib->max_redeemable_amount(100.0, 0.0));
        $this->assertSame('0.00', $lib->max_redeemable_amount(100.0, -5.0));
    }

    public function testPointValueBelowOneWithPercentCap(): void
    {
        $lib = $this->makeLib([
            'customer_reward_points_value'       => '0.10',
            'customer_reward_max_redeem_percent' => '25',
        ]);

        // Balance 100 points -> worth 10.00; 25% of 80.00 sale = 20.00;
        // limited by the smaller balance value of 10.00.
        $this->assertSame('10.00', $lib->max_redeemable_amount(100.0, 80.0));
    }
}
