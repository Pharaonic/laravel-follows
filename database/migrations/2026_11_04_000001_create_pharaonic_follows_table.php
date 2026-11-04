<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Pharaonic\Laravel\Follows\Models\Follow;

/**
 * One row per follower and followable pair. The ids are strings, so integer,
 * UUID and ULID keys can share the table, and the unique index is what
 * keeps a pair from being followed twice, even under concurrent requests.
 */
class CreatePharaonicFollowsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $name = (new Follow)->getTable();

        if (Schema::hasTable($name)) {
            throw new RuntimeException(sprintf(
                'The [%s] table already exists, so pharaonic/laravel-follows cannot create it. '
                .'Set pharaonic.follows.table to another name, or move the existing table out of the way.',
                $name
            ));
        }

        Schema::create($name, function (Blueprint $table) use ($name) {
            $table->bigIncrements('id');
            $table->string('follower_type', 191);
            $table->string('follower_id', 64);
            $table->string('followable_type', 191);
            $table->string('followable_id', 64);
            $table->timestamps();

            // Also serves "follows of this model": its first two columns.
            $table->unique(['followable_type', 'followable_id', 'follower_type', 'follower_id'], $name.'_unique_pair');
            $table->index(['follower_type', 'follower_id'], $name.'_follower_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists((new Follow)->getTable());
    }
}
