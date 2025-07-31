<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    use HasUuids;

    protected $primaryKey = 'post_id';
    protected $fillable = [
        'content',
        'user_id',
        'thread_id',
        'parrent_id'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class, 'thread_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'post_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'post_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Post::class, 'parent_id');
    }
    

    public function images(): HasMany
    {
        return $this->hasMany(Image::class,'post_id');
    }
}
