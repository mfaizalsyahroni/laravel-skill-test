<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    // /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;

    protected $fillable =
        [
            'user_id',
            'title',
            'content',
            // 'is_draft',
            // 'published_at',
        ];

    protected $casts = [
        'is_draft' => 'boolean',
        'published_at' => 'datetime',
    ];

    // User relationsip
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query
            ->where('is_draft', false)
            ->where('published_at', '<=', now());
    }
}
