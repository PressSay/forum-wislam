<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Image extends Model
{
    use HasUuids;
    protected $primaryKey = 'image_id';
    protected $fillable = [
        'url',
        'post_id',
        'thread_id',
    ];

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

        static::saving(function ($image) {
            $targets = [
                $image->thread_id,
                $image->post_id,
                $image->category_id
            ];

            // Đếm số liên kết không null
            $linked = array_filter($targets);

            if (count($linked) !== 1) {
                throw new \Exception('Exactly one of thread_id, post_id, or category_id must be set.');
            }
        });

        static::deleting(function ($product) {
            $disk = isset($_ENV['VAPOR_ARTIFACT_NAME']) ? 's3' : 'public';
            $path = 'images/' . $product->url;
            \Log::info($path);
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        });
    }
}
