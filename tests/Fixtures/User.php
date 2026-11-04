<?php

namespace Pharaonic\Laravel\Follows\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Pharaonic\Laravel\Follows\Concerns\CanFollow;
use Pharaonic\Laravel\Follows\Concerns\HasFollowers;

/**
 * Follows other models and is followed too.
 */
class User extends Authenticatable
{
    use CanFollow;
    use HasFollowers;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
