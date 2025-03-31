<?php

namespace App\Http\Controllers\Admin;


use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminUserResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;


class AdminUserController extends Controller
{
    public function index(): \Inertia\Response
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

    public function create(): \Inertia\Response
    {
        return Inertia::render("users/create");
    }

    public function store(): \Illuminate\Http\RedirectResponse
    {
        $validator = Validator::make(request()->all(), [
            User::COL_FIRST_NAME => [
                'required',
                'string',
                'min:1',
                'max:50'
            ],
            User::COL_LAST_NAME => [
                'required',
                'string',
                'min:1',
                'max:50'
            ],
            User::COL_EMAIL => [
                'required',
                'email',
                Rule::unique(User::TABLE)
            ],
            User::COL_PHONE_NUMBER => [
                'nullable',
                'string',
                'max:11',
                'min:11',
                Rule::unique(User::TABLE)
            ],
            User::COL_PASSWORD => [
                'nullable',
                'string',
                'min:6',
            ],
            User::COL_STATUS => [
                'boolean',
            ],
            User::COL_IS_ADMIN => [
                'boolean',
            ],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator->errors());
        }


        $data = $validator->validated();

        User::query()->create([
            User::COL_FIRST_NAME => $data['first_name'],
            User::COL_LAST_NAME => $data['last_name'],
            User::COL_EMAIL => $data['email'],
            User::COL_PASSWORD => $data['password'],
            User::COL_STATUS => $data['status'],
            User::COL_IS_ADMIN => $data['is_admin'],
            User::COL_PHONE_NUMBER => $data['phone_number'],
        ]);

        return redirect()->route('admin.users.list')->with('success', 'کاربر با موفقیت ایجاد شد.');


    }

    public function edit($id): \Inertia\Response|\Illuminate\Http\RedirectResponse
    {
        $user = User::query()->find($id);
        if (!$user) {
            return back()->with('error', 'کاربر پیدا نشد.');
        }

        $data = [
            User::COL_ID => $user[User::COL_ID],
            User::COL_LAST_NAME => $user[User::COL_LAST_NAME],
            User::COL_FIRST_NAME => $user[User::COL_FIRST_NAME],
            User::COL_EMAIL => $user[User::COL_EMAIL],
            User::COL_PASSWORD => $user[User::COL_PASSWORD],
            User::COL_STATUS => (bool)$user[User::COL_STATUS],
            User::COL_IS_ADMIN => (bool)$user[User::COL_IS_ADMIN],
            User::COL_PHONE_NUMBER => $user[User::COL_PHONE_NUMBER],
        ];

        return Inertia::render('users/edit', [
            'data' => $data
        ]);
    }

    public function show($id)
    {
        $user = User::query()->find($id);
        if (!$user) {
            return back()->with('error', 'کاربر پیدا نشد.');
        }

        $user = [
            User::COL_ID => $user[User::COL_ID],
            User::COL_LAST_NAME => $user[User::COL_LAST_NAME],
            User::COL_FIRST_NAME => $user[User::COL_FIRST_NAME],
            User::COL_EMAIL => $user[User::COL_EMAIL],
            User::COL_PASSWORD => $user[User::COL_PASSWORD],
            User::COL_STATUS => (bool)$user[User::COL_STATUS],
            User::COL_IS_ADMIN => (bool)$user[User::COL_IS_ADMIN],
            User::COL_PHONE_NUMBER => $user[User::COL_PHONE_NUMBER],
            Model::CREATED_AT => $user[Model::CREATED_AT],
            Model::UPDATED_AT => $user[Model::UPDATED_AT],
        ];

        return Inertia::render('users/show', [
            'user' => $user
        ]);
    }

    public function update($id): \Illuminate\Http\RedirectResponse
    {
        $validator = Validator::make(request()->all(), [
            User::COL_FIRST_NAME => [
                'required',
                'string',
                'min:1',
                'max:50'
            ],
            User::COL_LAST_NAME => [
                'required',
                'string',
                'min:1',
                'max:50'
            ],
            User::COL_EMAIL => [
                'required',
                'email',
                Rule::unique(User::TABLE)->ignore($id)
            ],
            User::COL_PHONE_NUMBER => [
                'nullable',
                'string',
                'max:11',
                'min:11',
                Rule::unique(User::TABLE)->ignore($id)
            ],
            User::COL_PASSWORD => [
                'nullable',
                'string',
                'min:6',
            ],
            User::COL_STATUS => [
                'boolean',
            ],
            User::COL_IS_ADMIN => [
                'boolean',
            ],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator->errors());
        }

        $user = User::query()->find($id);

        if (!$user) {
            return back()->with('error', 'کاربر پیدا نشد.');
        }


        $data = $validator->validated();


        if(empty($data['password'])){
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('admin.users.list')->with('success', 'کاربر با موفقیت ویرایش شد.');


    }

    public function destroy($id): \Illuminate\Http\RedirectResponse
    {
        $user = User::query()->find($id);
        $user->delete();
        return redirect()->route('admin.users.list')->with('success', 'کاربر با موفقیت حذف شد.');
    }
}
