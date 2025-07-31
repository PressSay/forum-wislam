<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasUuids;

    protected $primaryKey = 'notification_id';

    protected $fillable = ['user_id', 'content', 'is_read', 'thread_id', 'post_id'];
    protected $casts = [
        'is_read' => 'boolean',
    ];
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class, 'thread_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($notification) {
            if ($notification->thread_id && $notification->post_id) {
                throw new \Exception('Only one of thread_id or post_id can be set.');
            }
            if (!$notification->thread_id && !$notification->post_id) {
                throw new \Exception('One of thread_id or post_id must be set.');
            }
        });
    }
}
