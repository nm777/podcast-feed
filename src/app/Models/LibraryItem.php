<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LibraryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'media_file_id',
        'title',
        'description',
        'source_type',
        'source_url',
        'is_duplicate',
        'duplicate_detected_at',
    ];

    protected $casts = [
        'is_duplicate' => 'boolean',
        'duplicate_detected_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function mediaFile()
    {
        return $this->belongsTo(MediaFile::class);
    }
}
