<?php

namespace Pharaonic\Laravel\Follows\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Follows\Concerns\CanFollow;
use Pharaonic\Laravel\Follows\Concerns\HasFollowers;

/**
 * A UUID primary key, set on creation (HasUuids needs Laravel 9.30).
 */
class UuidModel extends Model
{
    use CanFollow;
    use HasFollowers;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var array<int, string>
     */
    protected $guarded = [];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->setAttribute($model->getKeyName(), Str::uuid()->toString());
        });
    }
}
