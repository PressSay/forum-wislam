<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Testing\Fluent\Concerns\Has;

class Tag extends Model
{
    use HasUuids;

    protected $primaryKey = 'tag_id';
    protected $fillable = [
        'name',
        'slug',
        'description'
    ];

    public function threads(): BelongsToMany
    {
        return $this->belongsToMany(Thread::class, 'thread_tag', 'tag_id', 'thread_id');
    }
}
