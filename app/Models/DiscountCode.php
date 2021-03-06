<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\ModelTraits\HelperForDiscountCode;
use OwenIt\Auditing\Contracts\Auditable;

class DiscountCode extends Model implements Auditable
{
    use HasFactory,
        HelperForDiscountCode,
        \OwenIt\Auditing\Auditable;

    /**
     * The attributes & relationships that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'audits', #Contain history changes of this model
    ];

    /**
     * Modify before store data changes in audit
     * Should add attributes in $hidden property above
     *
     * @var array
     * */
    protected $attributeModifiers = [];

    protected $primaryKey = 'discount_code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'discount_code',
        'price',
        'buyable',
        'name',
        'description',

        'maximum_price',
        'minimum_price',
        'maximum_discount',
        'minimum_discount',
        'percentage_discount',
        'direct_discount',
        'usable_at',
        'usable_closed_at',
        'offered_at',
        'offer_closed_at',
    ];

    protected $casts = [
        'discount_code' => 'string',
        'price' => 'integer',
        'buyable' => 'boolean',
        'name' => 'string',
        'description' => 'string',

        'maximum_price' => 'integer',
        'minimum_price' => 'integer',
        'maximum_discount' => 'integer',
        'minimum_discount' => 'integer',
        'percentage_discount' => 'integer',
        'direct_discount' => 'integer',
        'usable_at' => 'datetime',
        'usable_closed_at' => 'datetime',
        'offered_at' => 'datetime',
        'offer_closed_at' => 'datetime',
    ];

    /**
     * To set default
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Custom
        static::creating(function ($query) {
            $query->creator_id = $query->creator_id ?? optional(auth()->user())->id;
            $query->latest_updater_id = $query->latest_updater_id ?? optional(auth()->user())->id;
        });

        static::updating(function ($query) {
            $query->latest_updater_id = $query->latest_updater_id ?? optional(auth()->user())->id;
        });
    }

    /**
     * Relationship one-one with User
     * Include infos of model creator
     *
     * @return Illuminate\Database\Eloquent\Factories\Relationship
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Relationship one-one with User
     * Include infos of editor last updated model
     *
     * @return Illuminate\Database\Eloquent\Factories\Relationship
     */
    public function latestUpdater()
    {
        return $this->belongsTo(User::class, 'latest_updater_id');
    }

    /**
     * Polymorphic relationship many-many to \App\Models\Game
     *
     * @return Illuminate\Database\Eloquent\Factories\Relationship
     */
    public function supportedGames()
    {
        return $this->morphedByMany(
            Game::class,
            'model',
            'discount_code_supported_models',
        )
            ->withPivot('type_code')
            ->withTimestamps();
    }

    /**
     * Relationship many-many with Models\Users
     *
     * @return Illuminate\Database\Eloquent\Factories\Relationship
     */
    public function buyers()
    {
        return $this->belongsToMany(User::class, 'discount_code_has_been_bought_by_users', 'discount_code');
    }
}
