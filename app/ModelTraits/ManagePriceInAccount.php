<?php

namespace App\ModelTraits;

use App\Helpers\DiscountCodeHelper;
use App\Models\DiscountCode;

trait ManagePriceInAccount
{
    /**
     * To calculate temporary price to consult to buy
     * Not apply any discount code,
     * This is max price
     *
     * @return integer
     */
    public function calculateTemporaryPrice()
    {
        return $this->cost + $this->calculateFee();
    }

    /**
     * To calculate price to buy,
     * It's price user can buy it.
     *
     * @param string $discountCode
     * @return integer
     */
    public function calculatePrice($discountCode)
    {
        $discountCode = DiscountCodeHelper::musBeDiscountCode($discountCode);
    }
}