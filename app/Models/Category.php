<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasUuids;

    protected $primaryKey = 'category_id';
    protected $fillable = [
        'title',
        'slug',
        'description',
        'parrent_id'
    ];

    public function threads(): HasMany
    {
        return $this->hasMany(Thread::class, 'category_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'parrent_id', 'category_id');
    }

    public function parrentCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parrent_id', 'category_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class,'decentralization_of_categories','category_id', 'user_id');
    }

    public function blockedUsers()
    {
        return $this->belongsToMany(
            User::class,
            'users_blocked_from_categories',
            'category_id',
            'user_id'
        )->withTimestamps();
    }
}
