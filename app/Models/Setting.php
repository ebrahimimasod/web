<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    const TABLE = 'settings';
    const COL_NAME = 'name';
    const COL_VALUE = 'value';

    protected $table = self::TABLE;
    protected $fillable = [
        self::COL_NAME,
        self::COL_VALUE,
    ];
    public $timestamps = false;


}
