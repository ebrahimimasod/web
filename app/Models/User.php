<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    const TABLE = 'users';

    protected $table = self::TABLE;

    const COL_ID = 'id';
    const COL_LAST_NAME = 'last_name';
    const COL_FIRST_NAME = 'first_name';
    const COL_EMAIL = 'email';
    const COL_PASSWORD = 'password';
    const COL_STATUS = 'status';
    const COL_IS_ADMIN = 'is_admin';
    const COL_PHONE_NUMBER = 'phone_number';
    const COL_EMAIL_VERIFIED_AT = 'email_verified_at';
    const COL_PHONE_NUMBER_VERIFIED_AT = 'phone_number_verified_at';
    const COL_REMEMBER_TOKEN = 'remember_token';


    const  searchable = [
        self::COL_LAST_NAME,
        self::COL_FIRST_NAME,
        self::COL_EMAIL,
        self::COL_PHONE_NUMBER,
    ];


    protected $fillable = [
        self::COL_LAST_NAME,
        self::COL_FIRST_NAME,
        self::COL_EMAIL,
        self::COL_PASSWORD,
        self::COL_STATUS,
        self::COL_IS_ADMIN,
        self::COL_PHONE_NUMBER,
        self::COL_EMAIL_VERIFIED_AT,
        self::COL_PHONE_NUMBER_VERIFIED_AT,
        self::COL_REMEMBER_TOKEN,
    ];


    protected $hidden = [
        self::COL_PASSWORD,
        self::COL_REMEMBER_TOKEN,
    ];


    protected function casts(): array
    {
        return [
            self::COL_EMAIL_VERIFIED_AT => 'datetime',
            self::COL_PHONE_NUMBER_VERIFIED_AT => 'datetime',
            self::COL_PASSWORD => 'hashed',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if ($model->isDirty(self::COL_PASSWORD) && !Hash::isHashed($model[self::COL_PASSWORD])) {
                $model[self::COL_PASSWORD] = Hash::make($model[self::COL_PASSWORD]);
            }
        });
    }

}
