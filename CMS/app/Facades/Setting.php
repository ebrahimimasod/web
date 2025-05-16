<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array all()
 * @method static  get($name,$default=null)
 * @method static void set($name,$value)
 */
class Setting extends Facade
{
    protected static function getFacadeAccessor()
    {
        return  'setting';
    }
}
