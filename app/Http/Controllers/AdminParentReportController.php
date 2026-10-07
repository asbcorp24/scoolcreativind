<?php
namespace App\Http\Controllers;

use App\Models\ParentReportLink;
use App\Models\StudentProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminParentReportController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->is_admin || auth()->user()->canAdminSection('students'),403);

        return view('admin.academic.parent-reports',[
            'students'=>StudentProfile::with(['user','studio'])
                ->whereHas('user')
                ->orderBy('id')
                ->get()
                ->sortBy(fn($profile)=>mb_strtolower($profile->user->name ?? ''))
                ->values(),
            'links'=>ParentReportLink::with('student.user')
                ->latest()
                ->paginate(30),
            'selectedProfileId'=>$request->integer('student_profile_id'),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->is_admin || auth()->user()->canAdminSection('students'),403);

        $data=$request->validate([
            'student_profile_id'=>'required|exists:student_profiles,id',
            'report_until'=>'required|date',
        ]);

        $link=ParentReportLink::create([
            'student_profile_id'=>$data['student_profile_id'],
            'report_until'=>$data['report_until'],
            'token'=>Str::random(64),
            'is_active'=>true,
        ]);

        return redirect()
            ->route('admin.parent-reports')
            ->with('success','Ссылка для родителей создана.')
            ->with('parent_report_url',route('parent-report.show',$link->token));
    }

    public function revoke(ParentReportLink $link)
    {
        abort_unless(auth()->user()->is_admin || auth()->user()->canAdminSection('students'),403);

        $link->update(['is_active'=>false]);

        return back()->with('success','Родительская ссылка отозвана.');
    }

    public function regenerate(ParentReportLink $link)
    {
        abort_unless(auth()->user()->is_admin || auth()->user()->canAdminSection('students'),403);

        $link->update([
            'token'=>Str::random(64),
            'is_active'=>true,
            'last_opened_at'=>null,
        ]);

        return back()
            ->with('success','Ссылка пересоздана.')
            ->with('parent_report_url',route('parent-report.show',$link->token));
    }
}
