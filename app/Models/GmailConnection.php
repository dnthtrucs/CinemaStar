<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GmailConnection extends Model
{
    protected $fillable = ['provider', 'email', 'client_id', 'refresh_token', 'connected_by'];

    protected $hidden = ['refresh_token', 'client_id'];

    protected function casts(): array
    {
        return ['refresh_token' => 'encrypted'];
    }
}
