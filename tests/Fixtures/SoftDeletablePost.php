<?php

namespace Pharaonic\Laravel\Follows\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Pharaonic\Laravel\Follows\Concerns\HasFollowers;

class SoftDeletablePost extends Model
{
    use HasFollowers;
    use SoftDeletes;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
