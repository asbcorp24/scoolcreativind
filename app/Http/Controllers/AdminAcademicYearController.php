<?php
namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAcademicYearController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->is_admin,403);

        return view('admin.academic.academic-years',[
            'years'=>AcademicYear::withCount('groups')->orderByDesc('starts_on')->get(),
        ]);
    }

    public function save(Request $request, ?AcademicYear $year=null)
    {
        abort_unless(auth()->user()->is_admin,403);

        $data=$request->validate([
            'name'=>'required|string|max:40|unique:academic_years,name,'.optional($year)->id,
            'starts_on'=>'required|date',
            'ends_on'=>'required|date|after:starts_on',
            'is_current'=>'nullable|boolean',
        ]);

        $year ??= new AcademicYear();
        $before=$year->exists ? $year->toArray() : null;

        DB::transaction(function() use($request,$data,$year){
            if($request->boolean('is_current')){
                AcademicYear::where('id','<>',$year->id ?: 0)->update(['is_current'=>false]);
            }

            $data['is_current']=$request->boolean('is_current');
            $year->fill($data)->save();
        });

        ActivityLog::record(
            $before ? 'academic_year.updated' : 'academic_year.created',
            $year,
            'Учебный год: '.$year->name,
            $before,
            $year->fresh()->toArray()
        );

        return back()->with('success','Учебный год сохранён.');
    }

    public function toggleClosed(AcademicYear $year)
    {
        abort_unless(auth()->user()->is_admin,403);

        $before=$year->toArray();
        $year->is_closed=!$year->is_closed;

        if($year->is_closed){
            $year->is_current=false;
        }

        $year->save();

        ActivityLog::record(
            'academic_year.status',
            $year,
            ($year->is_closed ? 'Закрыт' : 'Открыт').' учебный год '.$year->name,
            $before,
            $year->fresh()->toArray()
        );

        return back()->with('success',$year->is_closed ? 'Учебный год закрыт и переведён в архив.' : 'Учебный год снова открыт.');
    }

    public function makeCurrent(AcademicYear $year)
    {
        abort_unless(auth()->user()->is_admin,403);
        abort_if($year->is_closed,422,'Нельзя сделать текущим закрытый учебный год.');

        DB::transaction(function() use($year){
            AcademicYear::query()->update(['is_current'=>false]);
            $year->update(['is_current'=>true]);
        });

        ActivityLog::record('academic_year.current',$year,'Текущий учебный год: '.$year->name);

        return back()->with('success','Текущий учебный год изменён.');
    }
}
