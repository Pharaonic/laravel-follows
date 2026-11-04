<?php

namespace Pharaonic\Laravel\Follows\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Follows\Concerns\HasFollowers;

class Comment extends Model
{
    use HasFollowers;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
