<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeedItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'feed_id',
        'library_item_id',
        'sequence',
    ];

    public function feed()
    {
        return $this->belongsTo(Feed::class);
    }

    public function libraryItem()
    {
        return $this->belongsTo(LibraryItem::class);
    }
}