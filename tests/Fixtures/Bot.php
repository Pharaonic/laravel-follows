<?php

namespace Pharaonic\Laravel\Follows\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Follows\Concerns\CanFollow;

/**
 * A follower that is not a user.
 */
class Bot extends Model
{
    use CanFollow;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
