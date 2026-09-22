<?php

namespace App\Libraries;

use Config\OSPOS;

/**
 * Reward library
 *
 * Loyalty/rewards rules: how many points a sale earns, what a point is worth,
 * and how many points a customer may redeem against a sale. Redemption is
 * governed by configurable rules so a store can require a minimum balance
 * before points may be spent and cap how much of a sale points may cover.
 *
 * Money math uses bcmath at the store's configured currency scale, matching
 * the rest of the sales code. The earn calculation intentionally mirrors the
 * historical formula (sale total * package percent / 100) so enabling this
 * library does not change the points existing installs award.
 */
class Reward_lib
{
    private array $settings;

    public function __construct(?array $settings = null)
    {
        $this->settings = $settings ?? (config(OSPOS::class)->settings ?? []);
    }

    /**
     * Points earned for a sale total at a given package earn percentage.
     *
     * Mirrors the historical earn formula and therefore returns a float.
     */
    public function calculate_points_earned(float $saleTotal, float $pointsPercent): float
    {
        return $saleTotal * $pointsPercent / 100;
    }

    /**
     * Monetary value of a single reward point, as configured by the store.
     * Defaults to 1 (one point is worth one unit of currency) which matches
     * the legacy behavior where points were redeemed one-for-one.
     */
    public function point_value(): string
    {
        $value = (string) ($this->settings['customer_reward_points_value'] ?? '1');

        return $value === '' ? '1' : $value;
    }

    /**
     * Minimum points a customer must hold before any redemption is allowed.
     * Defaults to 0 (no threshold).
     */
    public function min_redeem_points(): float
    {
        return (float) ($this->settings['customer_reward_min_redeem_points'] ?? 0);
    }

    /**
     * Maximum percentage of a sale total that reward points may cover.
     * 0 (the default) means no percentage cap.
     */
    public function max_redeem_percent(): float
    {
        return (float) ($this->settings['customer_reward_max_redeem_percent'] ?? 0);
    }

    /**
     * Whether the customer's balance meets the minimum-to-redeem threshold.
     */
    public function can_redeem(float $pointsBalance): bool
    {
        return $pointsBalance >= $this->min_redeem_points();
    }

    /**
     * Currency value of a number of points, at the configured point value and
     * currency scale.
     */
    public function points_to_currency(float $points, ?int $scale = null): string
    {
        $scale ??= $this->currency_scale();

        return bcmul((string) $points, $this->point_value(), $scale);
    }

    /**
     * Largest currency amount the customer may pay with points against a sale,
     * respecting: the minimum-to-redeem threshold, the value of their balance,
     * the max-percent-of-sale cap, and the sale total itself (points can never
     * pay more than the amount owed). Returns a bcmath string at currency scale.
     */
    public function max_redeemable_amount(float $pointsBalance, float $saleTotal, ?int $scale = null): string
    {
        $scale ??= $this->currency_scale();

        if (! $this->can_redeem($pointsBalance) || $saleTotal <= 0) {
            return bcadd('0', '0', $scale);
        }

        $limit = $this->points_to_currency($pointsBalance, $scale);
        $limit = $this->min_bc($limit, (string) $saleTotal, $scale);

        $percent = $this->max_redeem_percent();
        if ($percent > 0) {
            $percentCap = bcdiv(bcmul((string) $saleTotal, (string) $percent, $scale + 2), '100', $scale);
            $limit      = $this->min_bc($limit, $percentCap, $scale);
        }

        return bcadd($limit, '0', $scale);
    }

    private function currency_scale(): int
    {
        return (int) ($this->settings['currency_decimals'] ?? 2);
    }

    private function min_bc(string $a, string $b, int $scale): string
    {
        return bccomp($a, $b, $scale) <= 0 ? $a : $b;
    }
}
