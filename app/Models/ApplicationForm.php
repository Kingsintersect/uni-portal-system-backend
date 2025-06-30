<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ApplicationForm extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'user_id',
        'gender',
        'lga',
        'hometown',
        'hometown_address',
        'contact_address',
        'religion',
        'disability',
        'dob',
        'other_disability',
        'sponsor_name',
        'sponsor_relationship',
        'sponsor_phone_number',
        'sponsor_email',
        'sponsor_contact_address',
        'awaiting_result',
        'first_sitting',
        'second_sitting',
        'passport',
    ];

    public function applied_by()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
