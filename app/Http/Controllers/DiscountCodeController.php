<?php

namespace App\Http\Controllers;

use App\Models\DiscountCode;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\DiscountCodeResource;
use App\Http\Requests\StoreDiscountCodeRequest;
use App\Http\Requests\UpdateDiscountCodeRequest;
use Illuminate\Http\Request;

class DiscountCodeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreDiscountCodeRequest $request)
    {
        $discountCodeData = [];
        foreach ([
            'price', 'buyable', 'name', 'description',

            'maximumPrice', 'minimumPrice', 'maximumDiscount', 'minimumDiscount',
            'percentageDiscount', 'directDiscount',
            'usableAt', 'usableClosedAt', 'offeredAt', 'offerClosedAt', // timestamp
        ] as $key) {
            if ($request->filled($key)) {
                $discountCodeData[Str::snake($key)] = $request->$key;
            }
        }

        $discountCodeData['discount_code'] = $request->discountCode;

        try {
            DB::beginTransaction();

            $discountCode = DiscountCode::create($discountCodeData);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        return DiscountCodeResource::withLoadRelationships($discountCode->refresh());
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\DiscountCode  $discountCode
     * @return \Illuminate\Http\Response
     */
    public function show(DiscountCode $discountCode)
    {
        return DiscountCodeResource::withLoadRelationships($discountCode);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\DiscountCode  $discountCode
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateDiscountCodeRequest $request, DiscountCode $discountCode)
    {
        $discountCodeData = [];
        foreach ([
            'price', 'buyable', 'name', 'description',

            'maximumPrice', 'minimumPrice', 'maximumDiscount', 'minimumDiscount',
            'percentageDiscount', 'directDiscount',
            'usableAt', 'usableClosedAt', 'offeredAt', 'offerClosedAt', // timestamp
        ] as $key) {
            if ($request->filled($key)) {
                $discountCodeData[Str::snake($key)] = $request->$key;
            }
        }

        try {
            DB::beginTransaction();

            $discountCode->update($discountCodeData);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        return DiscountCodeResource::withLoadRelationships($discountCode);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\DiscountCode  $discountCode
     * @return \Illuminate\Http\Response
     */
    public function destroy(DiscountCode $discountCode)
    {
        try {
            DB::beginTransaction();

            $discountCode->supportedGames()->delete();
            $discountCode->buyers()->delete();
            $discountCode->delete();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        return response()->json([
            'message' => 'Xoá phiếu giảm giá thành công.',
        ], 200);
    }
}
