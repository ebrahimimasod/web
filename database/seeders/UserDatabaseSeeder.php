<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->truncate();

        User::query()->create([
            User::COL_FIRST_NAME => "مسعود",
            User::COL_LAST_NAME => "ابراهیمی",
            User::COL_EMAIL => "ebrahimimasod@gmail.com",
            User::COL_PASSWORD => bcrypt("12345678"),
            User::COL_STATUS => true,
            User::COL_IS_ADMIN => true,
            User::COL_PHONE_NUMBER => "09223173902",
            User::COL_EMAIL_VERIFIED_AT => now(),
            User::COL_PHONE_NUMBER_VERIFIED_AT => now(),
        ]);
//
//        for ($i = 0; $i < 100; $i++) {
//            User::query()->create([
//                User::COL_FIRST_NAME => "کاربر$i",
//                User::COL_LAST_NAME => "کاربری $i",
//                User::COL_EMAIL => "masoud$i@gmail.com",
//                User::COL_PASSWORD => null,
//                User::COL_STATUS => true,
//                User::COL_IS_ADMIN => false,
//                User::COL_PHONE_NUMBER => "092231739$i",
//                User::COL_EMAIL_VERIFIED_AT => now(),
//                User::COL_PHONE_NUMBER_VERIFIED_AT => now(),
//
//            ]);
//        }
    }
}
