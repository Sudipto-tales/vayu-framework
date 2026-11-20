<?php

namespace App\Models;

use Vayu\Database\Model;

class User extends Model
{
    /**
     * Mass-assignable attributes.
     *
     * @var list<string>
     */
    protected array $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];
}
