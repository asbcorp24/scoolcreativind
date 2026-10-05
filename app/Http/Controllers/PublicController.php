<?php
namespace App\Http\Controllers;

use App\Models\AdmissionApplication;
use App\Models\Event;
use App\Models\EquipmentItem;
use App\Models\TeamMember;
use App\Models\MediaLibraryItem;
use App\Models\NewsPost;
use App\Models\PortfolioItem;
use App\Models\Studio;
use App\Models\CompetitionRegistration;
use App\Models\QuizAttempt;
use App\Models\JournalEntry;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    public function home()
    {
        return view('home', [
            'studios' => Studio::where('is_active', true)->orderBy('sort_order')->get(),
            'featuredMedia' => MediaLibraryItem::where('attachable_type',Studio::class)->where('is_visible',true)->where('is_featured',true)->latest()->take(12)->get(),
            'projects' => PortfolioItem::with(['student.user','studio','media'=>fn($q)=>$q->where('is_visible',true)->orderBy('sort_order')])->where('is_public',true)->where('is_featured',true)->latest('completed_at')->take(8)->get(),
            'news' => NewsPost::where('is_published', true)->latest('published_at')->take(6)->get(),
            'events' => Event::where('is_published', true)->where('starts_at','>=',now()->subDay())->orderBy('starts_at')->take(4)->get(),
            'team' => TeamMember::where('is_active',true)->orderBy('sort_order')->take(4)->get(),
            'equipment' => EquipmentItem::where('is_featured',true)->with('studio')->orderBy('sort_order')->take(6)->get(),
        ]);
    }

    public function studio(Studio $studio)
    {
        abort_unless($studio->is_active, 404);
        $studio->load(['media'=>fn($q)=>$q->where('is_visible',true)->orderBy('sort_order'),'projects.media'=>fn($q)=>$q->where('is_visible',true)->orderBy('sort_order'),'projects.student.user','team','equipment']);
        return view('studio', compact('studio'));
    }

    public function team()
    {
        return view('team', ['members'=>TeamMember::where('is_active',true)->with('studio')->orderBy('sort_order')->get()]);
    }

    public function equipment()
    {
        return view('equipment', ['items'=>EquipmentItem::with('studio')->orderBy('sort_order')->get()]);
    }

    public function news()
    {
        return view('news.index', ['posts'=>NewsPost::where('is_published',true)->latest('published_at')->paginate(9)]);
    }

    public function newsShow(NewsPost $post)
    {
        abort_unless($post->is_published,404);
        $post->load(['media'=>fn($q)=>$q->where('is_visible',true)->orderBy('sort_order')]);
        return view('news.show', compact('post'));
    }

    public function apply()
    {
        return view('apply', ['studios'=>Studio::where('is_active',true)->orderBy('sort_order')->get()]);
    }

    public function storeApplication(Request $request)
    {
        $data=$request->validate([
            'name'=>'required|string|max:160','birth_date'=>'nullable|date','phone'=>'required|string|max:40',
            'email'=>'nullable|email|max:160','studio_id'=>'nullable|exists:studios,id','message'=>'nullable|string|max:3000'
        ]);
        $data['user_id']=auth()->id();
        AdmissionApplication::create($data);
        return back()->with('success','Заявка отправлена. Мы свяжемся с вами после обработки.');
    }

    public function cabinet()
    {
        $user=auth()->user();
        $groupIds=$user->studentGroups()->pluck('study_groups.id');

        $attendanceEntries=JournalEntry::with(['lesson.subject'])
            ->where('student_id',$user->id)
            ->whereHas('lesson',function($q) use($groupIds){
                $q->whereIn('study_group_id',$groupIds)
                    ->whereDate('lesson_date','<=',now()->toDateString());
            })
            ->get();

        $attendanceTotal=$attendanceEntries->count();
        $present=$attendanceEntries->where('attendance','present')->count();
        $late=$attendanceEntries->where('attendance','late')->count();

        $attendanceSummary=[
            'total'=>$attendanceTotal,
            'present'=>$present,
            'late'=>$late,
            'absent'=>$attendanceEntries->where('attendance','absent')->count(),
            'excused'=>$attendanceEntries->where('attendance','excused')->count(),
            'rate'=>$attendanceTotal ? round((($present+$late)/$attendanceTotal)*100,1) : null,
        ];

        $attendanceBySubject=$attendanceEntries
            ->groupBy(fn($entry)=>$entry->lesson?->subject_id ?: 0)
            ->map(function($items){
                $total=$items->count();
                $present=$items->where('attendance','present')->count();
                $late=$items->where('attendance','late')->count();

                return [
                    'subject'=>$items->first()?->lesson?->subject?->title ?: 'Без предмета',
                    'total'=>$total,
                    'present'=>$present,
                    'late'=>$late,
                    'absent'=>$items->where('attendance','absent')->count(),
                    'excused'=>$items->where('attendance','excused')->count(),
                    'rate'=>$total ? round((($present+$late)/$total)*100,1) : 0,
                ];
            })
            ->sortBy('subject')
            ->values();

        return view('cabinet', [
            'applications'=>AdmissionApplication::where('user_id',$user->id)->latest()->get(),
            'competitionRegistrations'=>CompetitionRegistration::with(['competition','documents'])->where('user_id',$user->id)->latest()->get(),
            'quizCertificates'=>QuizAttempt::with('quiz')->where('user_id',$user->id)->where('passed',true)->whereNotNull('certificate_code')->latest('completed_at')->get(),
            'attendanceSummary'=>$attendanceSummary,
            'attendanceBySubject'=>$attendanceBySubject,
        ]);
    }
}
