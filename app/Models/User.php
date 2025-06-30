<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable2 = [
        'name',
        'email',
        'password',
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
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'student_course_enrolments', 'user_id', 'course_id');
    }


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






    public static function approvedRoles()
    {
        return ['ADMIN', 'STUDENT', 'TEACHER', 'MANAGER'];
    }

    public static function approvedPrograms()
    {
        return [1 => 'DEGREE', 2 => 'PHD', 3 => 'PGD', 4 => 'MSC'];
    }

    public static function checkAdminAuthority(): Bool
    {
        $user = auth('api')->user();
        if ($user) {
            if (!$user || $user->role != 'ADMIN') {
                return false;
            }
            return true;
        } else {
            return false;
        }
    }
}
