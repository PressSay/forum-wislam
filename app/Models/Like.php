<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // Add this trait

class Like extends Model
{
    // REMOVE: use HasUuids; // Remove this line

    // Since you have a composite primary key and it's not 'id',
    // you need to set $incrementing to false.
    public $incrementing = false;

    // You also need to specify the type of your primary keys if they are not integers.
    // In your case, they are UUIDs.
    protected $keyType = 'string'; // Keep this as it correctly describes the key types

    // The columns that can be mass assigned
    protected $fillable = [
        'user_id',
        'thread_id'
    ];

    // IMPORTANT: You do not need to define $primaryKey as an array.
    // For composite keys, you rely on the database schema and query with 'where'.
    // Laravel's Eloquent does not natively handle $primaryKey as an array for
    // its internal operations like 'find()' or automatic ID generation.

    /**
     * Relationship with User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relationship with Thread
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class, 'thread_id');
    }
}

