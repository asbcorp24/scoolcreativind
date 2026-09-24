<?php
namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\Competition;
use App\Models\CompetitionRegistration;
use App\Models\PortfolioItem;
use App\Models\ScheduleLesson;
use App\Models\StudentProfile;
use Illuminate\Http\Request;

class LearningController extends Controller
{
    public function schedule(Request $request)
    {
        $month=$request->input('month', now()->format('Y-m'));
        $start=\Carbon\Carbon::createFromFormat('Y-m',$month)->startOfMonth();
        $end=$start->copy()->endOfMonth();

        $query=ScheduleLesson::with(['studio','group'])->where('is_published',true);

        if (auth()->check() && !auth()->user()->is_admin) {
            $groupIds=auth()->user()->studentGroups()->pluck('study_groups.id');
            $query->where(function($q) use ($groupIds){
                $q->whereIn('study_group_id',$groupIds)->orWhereNull('study_group_id');
            });
        }

        $lessons=$query
            ->whereBetween('lesson_date',[$start->toDateString(),$end->toDateString()])
            ->orderBy('lesson_date')->orderBy('starts_at')->get();

        return view('schedule',compact('lessons','start','month'));
    }

    public function portfolio(StudentProfile $profile)
    {
        abort_unless($profile->is_public || (auth()->check() && auth()->id()===$profile->user_id),404);
        $profile->load(['user','studio','portfolio.studio','achievements.competition']);
        return view('portfolio.show',compact('profile'));
    }

    public function myPortfolio()
    {
        $profile=StudentProfile::firstOrCreate(
            ['user_id'=>auth()->id()],
            ['portfolio_slug'=>'student-'.auth()->id(), 'is_public'=>false]
        );
        $profile->load(['user','studio','portfolio.studio','achievements.competition']);
        return view('portfolio.mine',compact('profile'));
    }

    public function competitions()
    {
        return view('competitions.index',[
            'competitions'=>Competition::where('is_published',true)->withCount('registrations')->latest('starts_on')->get(),
            'registrations'=>auth()->check()
                ? CompetitionRegistration::where('user_id',auth()->id())->get()->keyBy('competition_id')
                : collect(),
            'achievements'=>Achievement::where('is_public',true)->with(['student.user','competition'])->latest('awarded_at')->take(24)->get(),
        ]);
    }
}
