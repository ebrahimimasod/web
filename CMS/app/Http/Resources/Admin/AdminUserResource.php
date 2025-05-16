<?php

namespace App\Http\Resources\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int)$this[User::COL_ID],
            'first_name' => (string)$this[User::COL_FIRST_NAME],
            'last_name' => (string)$this[User::COL_LAST_NAME],
            'email' => (string)$this[User::COL_EMAIL],
            'status' => (boolean)$this[User::COL_STATUS],
            'is_admin' => (boolean)$this[User::COL_IS_ADMIN],
            'phone_number' => (string)$this[User::COL_PHONE_NUMBER],
            'email_verified_at' => $this[User::COL_EMAIL_VERIFIED_AT],
            'phone_number_verified_at' => $this[User::COL_PHONE_NUMBER_VERIFIED_AT],
            'created_at' => $this[Model::CREATED_AT],
            'updated_at' => $this[Model::UPDATED_AT],
        ];
    }
}
