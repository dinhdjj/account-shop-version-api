<?php

namespace App\Http\Controllers;

use App\Http\Resources\DiscountCodeResource;
use App\Models\DiscountCode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiscountCodeTradingController extends Controller
{
    /**
     * User buy a account.
     *
     * @param  \App\Models\DiscountCode  $discountCode
     * @return \Illuminate\Http\Response
     */
    public function buy(DiscountCode $discountCode)
    {
        $result = false;
        try {
            DB::beginTransaction();

            if (
                auth()->user()->reduceSilverCoin($discountCode->price)
            ) {
                $result = true;
                $discountCode->buyers()->attach(auth()->user()->getKey());
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        if ($result) {
            return DiscountCodeResource::withLoadRelationships($discountCode);
        } else {
            return response()->json([
                'message' => 'Bạn không đủ tiền hoặc sao đó.',
            ], 422);
        }
    }
}
