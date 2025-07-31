<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasUuids;
    protected $primaryKey = 'conversation_id';

    public function users()
    {
        return $this->belongsToMany(User::class, 'conversation_user', 'conversation_id', 'user_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'conversation_id', 'conversation_id');
    }
}
