<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class AdminAuditController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->is_admin,403);

        $query=ActivityLog::with('user')->latest();

        if($request->filled('action')){
            $query->where('action','like','%'.$request->input('action').'%');
        }

        if($request->filled('user_id')){
            $query->where('user_id',$request->integer('user_id'));
        }

        if($request->filled('date_from')){
            $query->whereDate('created_at','>=',$request->input('date_from'));
        }

        if($request->filled('date_to')){
            $query->whereDate('created_at','<=',$request->input('date_to'));
        }

        return view('admin.audit.index',[
            'logs'=>$query->paginate(50)->withQueryString(),
            'users'=>\App\Models\User::whereIn('id',ActivityLog::whereNotNull('user_id')->distinct()->pluck('user_id'))
                ->orderBy('name')
                ->get(),
        ]);
    }
}
