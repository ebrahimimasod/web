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
        $keyword = request('keyword');
        $searchable = User::searchable;

        $users = User::query()
            ->where(function ($query) use ($keyword, $searchable) {
                if ($keyword) {
                    foreach ($searchable as $column) {
                        $query->orWhere($column, 'like', "%{$keyword}%");
                    }
                }
            })
            ->latest()
            ->paginate();
        $users = AdminUserResource::collection($users);
        return Inertia::render('users/list', [
            'users' => $users,
            'keyword' => $keyword
        ]);
    }

    public function destroy($id)
    {
        $user = User::query()->find($id);
        $user->delete();
        return back()->with('warning', 'کاربر با موفقیت حذف شد.');
    }
}
