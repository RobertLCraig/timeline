<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UploadFlag extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'filename',
        'url',
        'uploader_user_id',
        'scores',
        'top_score',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'scores'      => 'array',
        'top_score'   => 'float',
        'reviewed_at' => 'datetime',
        'created_at'  => 'datetime',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploader_user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
