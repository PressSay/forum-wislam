<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProfileImage extends Model
{
    use HasUuids;
    protected $primaryKey = 'profile_image_id';
    protected $fillable = [
        'user_id',
        'original_url',
    ];


    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }    
}
