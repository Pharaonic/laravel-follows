<?php

namespace Pharaonic\Laravel\Follows\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Uses neither trait: a follower can still follow it.
 */
class Product extends Model
{
    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
