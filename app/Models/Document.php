<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = [
        'original_filename',
        'stored_filename',
        'status',
        'ocr_text',
        'confidence_score',
        'failure_reason',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
