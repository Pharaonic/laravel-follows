<?php

namespace Pharaonic\Laravel\Follows\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Pharaonic\Laravel\Follows\Concerns\CanFollow;

class SoftDeletableMember extends Model
{
    use CanFollow;
    use SoftDeletes;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
