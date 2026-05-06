<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class UserController extends Controller
{
    public function index()
    {
        $totalData = User::count();
        $newData = User::where('created_at', '>=', now()->subDays(7))->count();
        $activeData = User::where('is_active', true)->count();
        $updatedToday = User::whereDate('updated_at', today())->count();

        return view('page.user.index', compact(
            'totalData',
            'newData',
            'activeData',
            'updatedToday'
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
                        return '<span class="label label-inline label-light-primary font-weight-bold text-uppercase">' . $role->name . '</span>';
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
                    $btn = '';

                    $btn .= '<a href="javascript:void(0)" class="btn btn-sm btn-outline-warning btn-icon mr-2" title="Edit details">
                                <i class="fas fa-pencil-alt"></i>
                            </a>';

                    return $btn ?: '<span class="text-muted">No Access</span>';
                })
                ->rawColumns(['role', 'is_active', 'action'])
                ->make(true);
        }
    }
}
