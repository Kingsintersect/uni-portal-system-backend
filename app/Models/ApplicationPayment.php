<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;

class ApplicationPayment extends Model implements Authenticatable, JWTSubject
{
    use SoftDeletes, HasFactory, AuthenticatableTrait;

    protected $table = 'application_payments';
    protected $fillable2 = [
        'last_name',
        'first_name',
        'other_name',
        'level',
        'faculty_id',
        'department_id',
        'nationality',
        'state',
        'phone_number',
        'email',
        'password',
        'reference',
        'amount',
        'is_applied',
        'admission_status',
        'acceptance_fee_payment_status',
        'tuition_payment_status',
        'application_payment_status',
        'reg_number',
        'reason_for_denial',
        'disability',
    ];

    protected $fillable = [
        'last_name',
        'first_name',
        'other_name',
        'username',
        'role',
        'program',
        'level',
        'faculty_id',
        'department_id',
        'nationality',
        'state',
        'phone_number',
        'email',
        'password',
        'reference',
        'amount',
        'is_applied',
        'admission_status',
        'acceptance_fee_payment_status',
        'tuition_payment_status',
        'application_payment_status',
        'reg_number',
        'academic_session',
        'reason_for_denial'
    ];

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function application()
    {
        // Linking each payment to one application form using 'user_id'
        return $this->belongsTo(ApplicationForm::class, 'id', 'user_id');
    }
}
