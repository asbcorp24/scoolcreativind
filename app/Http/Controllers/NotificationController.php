<?php
namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private function queryForUser($user): Builder
    {
        $query=Announcement::with(['group','author'])
            ->where('is_published',true)
            ->where(function($q){
                $q->whereNull('published_at')->orWhere('published_at','<=',now());
            })
            ->where(function($q){
                $q->whereNull('expires_at')->orWhere('expires_at','>=',now());
            });

        if($user->is_admin){
            return $query;
        }

        $isTeacher=$user->isTeacherRole();

        $groupIds=$isTeacher
            ? $user->teacherGroups()->pluck('study_groups.id')
            : $user->studentGroups()->pluck('study_groups.id');

        return $query
            ->whereIn('audience',$isTeacher ? ['all','teachers'] : ['all','students'])
            ->where(function($q) use($groupIds){
                $q->whereNull('study_group_id')
                  ->orWhereIn('study_group_id',$groupIds);
            });
    }

    public function index()
    {
        $user=auth()->user();

        $announcements=$this->queryForUser($user)
            ->withExists([
                'reads as is_read'=>fn($q)=>$q->where('user_id',$user->id),
            ])
            ->latest('published_at')
            ->paginate(30);

        return view('notifications.index',compact('announcements'));
    }

    public function read(Announcement $announcement)
    {
        $user=auth()->user();

        abort_unless(
            $this->queryForUser($user)->whereKey($announcement->id)->exists(),
            403
        );

        AnnouncementRead::updateOrCreate(
            [
                'announcement_id'=>$announcement->id,
                'user_id'=>$user->id,
            ],
            ['read_at'=>now()]
        );

        return back();
    }

    public function readAll()
    {
        $user=auth()->user();

        $ids=$this->queryForUser($user)->pluck('id');

        foreach($ids as $announcementId){
            AnnouncementRead::updateOrCreate(
                [
                    'announcement_id'=>$announcementId,
                    'user_id'=>$user->id,
                ],
                ['read_at'=>now()]
            );
        }

        return back()->with('success','Все уведомления отмечены прочитанными.');
    }
}
