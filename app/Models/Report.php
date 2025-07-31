<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Report extends Model
{
    use HasFactory;

    /**
     * Get the value of the model's primary key.
     *
     * @param  string|null  $key
     * @return mixed
     */
    public function getKey($key = null)
    {
        if ($key) {
            return $this->getAttribute($key);
        }

        // Return an array of key values for composite keys
        return [
            'user_id' => $this->getAttribute('user_id'),
            'thread_id' => $this->getAttribute('thread_id'),
        ];
    }

    /**
     * Set the keys for a save update query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSaveQuery($query)
    {
        $query->where('user_id', $this->getAttribute('user_id'))
            ->where('thread_id', $this->getAttribute('thread_id'));

        return $query;
    }

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['user_id', 'thread_id', 'reason', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class, 'thread_id');
    }
}
