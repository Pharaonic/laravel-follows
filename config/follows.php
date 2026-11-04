<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Follows table
    |--------------------------------------------------------------------------
    |
    | The table that stores every follow. The package migration creates it with
    | this name, and the Follow model reads and writes it. Changing it after the
    | migration ran does not rename the existing table.
    |
    */

    'table' => 'follows',

    /*
    |--------------------------------------------------------------------------
    | Clean up on delete
    |--------------------------------------------------------------------------
    |
    | When a model that uses CanFollow or HasFollowers is deleted (or force deleted,
    | when it uses SoftDeletes), delete the follows it gave or received. A soft
    | delete always keeps them, so a restored model keeps its follows.
    |
    */

    'cleanup_on_delete' => true,

];
