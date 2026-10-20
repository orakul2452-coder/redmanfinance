<?php
require_once __DIR__ . '/../referral_rewards.php';

function check_promo($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
    echo "PASS: $message\n";
}

$belowMinimum = redman_referral_promo_amounts(4999.99, true);
check_promo($belowMinimum['discount'] === 0.0 && $belowMinimum['payable'] === 4999.99, 'investment below 5000 receives no referral promotion');

$atMinimum = redman_referral_promo_amounts(5000.00, true);
check_promo($atMinimum['discount'] === 500.0 && $atMinimum['payable'] === 4500.0, 'qualifying referred investment pays 500 less at the 5000 threshold');

$withoutReferral = redman_referral_promo_amounts(7500.00, false);
check_promo($withoutReferral['discount'] === 0.0 && $withoutReferral['payable'] === 7500.0, 'investment without a qualified referral pays full plan amount');

$smallInvestment = redman_referral_promo_amounts(100.00, true);
check_promo($smallInvestment['discount'] === 0.0 && $smallInvestment['payable'] === 100.0, '100 investment does not receive or overdraw cashback');