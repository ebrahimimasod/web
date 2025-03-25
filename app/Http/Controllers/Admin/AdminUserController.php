<?php

namespace App\Http\Controllers\Admin;


use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminUserResource;
use App\Models\User;
use Inertia\Inertia;

class AdminUserController extends Controller
{
    public function index()
    {
        $users = User::query()->latest()->paginate();
        $users = AdminUserResource::collection($users);
        return Inertia::render('users/list', [
            'users' => $users
        ]);
    }
}
