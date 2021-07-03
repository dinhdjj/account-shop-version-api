<?php

namespace App\PivotModels;

use Illuminate\Database\Eloquent\Relations\Pivot;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Redactors\RightRedactor;

class AccountAccountAction extends Pivot implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    /**
     * The attributes & relationships that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'is_done',
        'audits', #Contain history changes of this model
    ];

    /**
     * Modify before store data changes in audit
     * Should add attributes in $hidden property above
     *
     * @var array
     * */
    protected $attributeModifiers = [
        'is_done' => RightRedactor::class,
    ];

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The attributes should be cast
     *
     * @var array
     */
    protected $casts = [
        'is_done' => 'boolean',
    ];
}
