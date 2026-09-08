<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\DataTables;

class UserController extends Controller
{
    public function index()
    {
        $totalData = User::count();
        $newData = User::where('created_at', '>=', now()->subDays(7))->count();
        $activeData = User::where('is_active', true)->count();
        $updatedToday = User::whereDate('updated_at', today())->count();
        $roles = Role::pluck('name', 'name');

        return view('page.user.index', compact(
            'totalData',
            'newData',
            'activeData',
            'updatedToday',
            'roles'
        ));
    }

    public function getData(Request $request)
    {
        if ($request->ajax()) {
            $data = User::with('roles')->select('users.*');

            return DataTables::of($data)
                ->addIndexColumn()

                ->editColumn('role', function ($row) {
                    return $row->roles->map(function ($role) {
                        return '<span class="label label-inline label-light-primary font-weight-bold text-uppercase text-nowrap">' . $role->name . '</span>';
                    })->implode(' ');
                })

                ->filterColumn('role', function ($query, $keyword) {
                    $query->whereHas('roles', function ($q) use ($keyword) {
                        $q->where('name', 'like', "%{$keyword}%");
                    });
                })

                ->editColumn('is_active', function ($row) {
                    return $row->is_active ? '<span class="label label-inline label-light-success">Active</span>' : '<span class="label label-inline label-light-danger">Inactive</span>';
                })

                ->addColumn('action', function ($row) {
                    $roleName = $row->roles->first() ? $row->roles->first()->name : '';
                    $btn = '<button type="button" class="btn btn-sm btn-outline-warning btn-icon mr-2 edit-btn" 
                                data-id="' . $row->id . '" 
                                data-nik="' . $row->nik . '"
                                data-name="' . $row->name . '"
                                data-email="' . $row->email . '"
                                data-jabatan="' . $row->jabatan . '"
                                data-role="' . $roleName . '"
                                data-is_active="' . $row->is_active . '"
                                title="Edit details">
                                <i class="fas fa-pencil-alt"></i>
                            </button>';
                    
                    $btn .= '<button type="button" class="btn btn-sm btn-outline-danger btn-icon delete-btn" 
                                data-id="' . $row->id . '" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>';

                    return $btn;
                })
                ->rawColumns(['role', 'is_active', 'action'])
                ->make(true);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nik' => ['required', Rule::unique('users')->whereNull('deleted_at')],
            'name' => 'required',
            'email' => ['required', 'email', Rule::unique('users')->whereNull('deleted_at')],
            'jabatan' => 'nullable',
            'role' => 'required',
        ]);

        $data['is_active'] = $request->has('is_active') ? 1 : 0;
        $data['password'] = bcrypt($data['nik']);
        
        $user = User::create($data);
        $user->assignRole($request->role);

        return response()->json(['success' => 'User created successfully.']);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'nik' => ['required', Rule::unique('users')->ignore($user->id)->whereNull('deleted_at')],
            'name' => 'required',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)->whereNull('deleted_at')],
            'jabatan' => 'nullable',
            'role' => 'required',
        ]);

        $data['is_active'] = $request->has('is_active') ? 1 : 0;
        $user->update($data);
        $user->syncRoles([$request->role]);

        return response()->json(['success' => 'User updated successfully.']);
    }

    public function destroy(User $user)
    {
        $user->delete();
        return response()->json(['success' => 'User deleted successfully.']);
    }
}
