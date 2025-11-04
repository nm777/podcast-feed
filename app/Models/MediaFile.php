<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MediaFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_path',
        'file_hash',
        'mime_type',
        'filesize',
        'duration',
    ];

    public function libraryItems()
    {
        return $this->hasMany(LibraryItem::class);
    }
}
