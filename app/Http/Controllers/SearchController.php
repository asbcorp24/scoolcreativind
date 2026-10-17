<?php
namespace App\Http\Controllers;

use App\Models\CustomPage;
use App\Models\JournalLesson;
use App\Models\NewsPost;
use App\Models\OfficialDocument;
use App\Models\PortfolioItem;
use App\Models\ScheduleLesson;
use App\Models\Studio;
use App\Models\SubjectLesson;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q=trim((string)$request->input('q',''));
        $results=collect();

        if(mb_strlen($q)>=2){
            $news=NewsPost::where('is_published',true)
                ->where(function($query) use($q){
                    $query->where('title','like','%'.$q.'%')
                        ->orWhere('excerpt','like','%'.$q.'%')
                        ->orWhere('body','like','%'.$q.'%');
                })
                ->take(10)->get();

            foreach($news as $item){
                $results->push([
                    'type'=>'Новость',
                    'title'=>$item->title,
                    'text'=>$item->excerpt,
                    'url'=>route('news.show',$item),
                ]);
            }

            $documents=OfficialDocument::where('is_published',true)
                ->where(function($query) use($q){
                    $query->where('title','like','%'.$q.'%')
                        ->orWhere('description','like','%'.$q.'%')
                        ->orWhere('category','like','%'.$q.'%');
                })
                ->take(10)->get();

            foreach($documents as $item){
                $results->push([
                    'type'=>$item->type_label,
                    'title'=>$item->title,
                    'text'=>$item->description,
                    'url'=>$item->target_url ?: route('documents.index'),
                ]);
            }

            $pages=CustomPage::where('is_published',true)
                ->where(function($query) use($q){
                    $query->where('title','like','%'.$q.'%')
                        ->orWhere('subtitle','like','%'.$q.'%')
                        ->orWhere('body_html','like','%'.$q.'%');
                })
                ->take(10)->get();

            foreach($pages as $item){
                $results->push([
                    'type'=>'Страница',
                    'title'=>$item->title,
                    'text'=>$item->subtitle,
                    'url'=>route('pages.show',$item),
                ]);
            }

            $projects=PortfolioItem::where('is_public',true)
                ->where(function($query) use($q){
                    $query->where('title','like','%'.$q.'%')
                        ->orWhere('description','like','%'.$q.'%');
                })
                ->take(10)->get();

            foreach($projects as $item){
                $results->push([
                    'type'=>'Работа',
                    'title'=>$item->title,
                    'text'=>$item->description,
                    'url'=>route('projects.show',$item),
                ]);
            }

            $studios=Studio::where('is_active',true)
                ->where(function($query) use($q){
                    $query->where('title','like','%'.$q.'%')
                        ->orWhere('subtitle','like','%'.$q.'%')
                        ->orWhere('description','like','%'.$q.'%');
                })
                ->take(10)->get();

            foreach($studios as $item){
                $results->push([
                    'type'=>'Студия',
                    'title'=>$item->title,
                    'text'=>$item->subtitle,
                    'url'=>route('studios.show',$item),
                ]);
            }

            $schedule=ScheduleLesson::where('is_published',true)
                ->where(function($query) use($q){
                    $query->where('title','like','%'.$q.'%')
                        ->orWhere('teacher_name','like','%'.$q.'%')
                        ->orWhere('room','like','%'.$q.'%')
                        ->orWhere('description','like','%'.$q.'%');
                })
                ->orderBy('lesson_date')
                ->take(10)->get();

            foreach($schedule as $item){
                $results->push([
                    'type'=>'Расписание',
                    'title'=>$item->title,
                    'text'=>$item->lesson_date->format('d.m.Y').' · '.substr($item->starts_at,0,5).' · '.$item->teacher_name,
                    'url'=>route('schedule',['month'=>$item->lesson_date->format('Y-m')]),
                ]);
            }

            if(auth()->check()){
                $user=auth()->user();

                if($user->is_admin || $user->isTeacherRole()){
                    $lessons=SubjectLesson::with('subject')
                        ->where('is_published',true)
                        ->when(!$user->is_admin,function($query) use($user){
                            $query->whereHas('subject.groups',fn($g)=>$g->where('group_subjects.teacher_id',$user->id));
                        })
                        ->where(function($query) use($q){
                            $query->where('title','like','%'.$q.'%')
                                ->orWhere('content','like','%'.$q.'%');
                        })
                        ->take(15)->get();

                    foreach($lessons as $item){
                        $results->push([
                            'type'=>'КТП / урок',
                            'title'=>'Урок '.$item->lesson_number.' · '.$item->title,
                            'text'=>$item->subject->title ?? '',
                            'url'=>route('admin.subjects.lessons',$item->subject_id),
                        ]);
                    }
                }else{
                    $groupIds=$user->studentGroups()->pluck('study_groups.id');

                    $lessons=JournalLesson::with(['subject','group'])
                        ->whereIn('study_group_id',$groupIds)
                        ->where(function($query) use($q){
                            $query->where('topic','like','%'.$q.'%')
                                ->orWhereHas('planLesson',function($plan) use($q){
                                    $plan->where('content','like','%'.$q.'%')
                                         ->orWhere('title','like','%'.$q.'%');
                                });
                        })
                        ->latest('lesson_date')
                        ->take(15)->get();

                    foreach($lessons as $item){
                        $results->push([
                            'type'=>'Мой урок',
                            'title'=>$item->topic,
                            'text'=>($item->subject->title ?? '').' · '.($item->group->name ?? ''),
                            'url'=>route('academic.lesson',$item),
                        ]);
                    }
                }
            }
        }

        return view('search.index',[
            'query'=>$q,
            'results'=>$results->take(60)->values(),
        ]);
    }
}
