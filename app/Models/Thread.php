<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


class Thread extends Model
{
    use HasUuids;

    protected $primaryKey = 'thread_id';
    protected $fillable = [
        'title',
        'slug',
        'content',
        'user_id',
        'category_id',
        'is_locked',
        'is_pinned'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'thread_id');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class, 'thread_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'thread_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'thread_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'thread_tag', 'thread_id', 'tag_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(Image::class, 'thread_id');
    }
}
