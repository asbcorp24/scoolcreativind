<?php
namespace App\Providers;

use App\Models\Announcement;
use App\Models\SiteSetting;
use App\Models\MusicTrack;
use App\Models\CustomPage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        $settings=[];
        try {
            if (Schema::hasTable('site_settings')) {
                $settings=SiteSetting::pluck('value','key')->all();
            }
        } catch (\Throwable $e) {
            $settings=[];
        }

        View::share('siteSettings',$settings);

        $musicTracks=collect();
        try {
            if (Schema::hasTable('music_tracks')) {
                $musicTracks=MusicTrack::where('is_active',true)->orderBy('sort_order')->orderBy('id')->get();
            }
        } catch (\Throwable $e) {
            $musicTracks=collect();
        }

        View::share('musicTracks',$musicTracks);

        $customMenuPages=collect();
        try {
            if (Schema::hasTable('custom_pages')) {
                $customMenuPages=CustomPage::whereNull('parent_id')
                    ->where('is_published',true)
                    ->where('show_in_menu',true)
                    ->with(['children'=>fn($q)=>$q->where('is_published',true)->where('show_in_menu',true)])
                    ->orderBy('sort_order')->orderBy('title')->get();
            }
        } catch (\Throwable $e) {
            $customMenuPages=collect();
        }

        View::share('customMenuPages',$customMenuPages);

        View::composer('layouts.app',function($view){
            $userAnnouncements=collect();

            try {
                if(auth()->check() && Schema::hasTable('announcements')){
                    $user=auth()->user();
                    $query=Announcement::with('group')
                        ->where('is_published',true)
                        ->where(function($q){
                            $q->whereNull('published_at')->orWhere('published_at','<=',now());
                        })
                        ->where(function($q){
                            $q->whereNull('expires_at')->orWhere('expires_at','>=',now());
                        });

                    if(!$user->is_admin){
                        $isTeacher=$user->isTeacherRole();
                        $groupIds=$isTeacher
                            ? $user->teacherGroups()->pluck('study_groups.id')
                            : $user->studentGroups()->pluck('study_groups.id');

                        $query->whereIn('audience',$isTeacher ? ['all','teachers'] : ['all','students'])
                            ->where(function($q) use($groupIds){
                                $q->whereNull('study_group_id')
                                  ->orWhereIn('study_group_id',$groupIds);
                            });
                    }

                    if(Schema::hasTable('announcement_reads')){
                        $query->whereDoesntHave('reads',fn($q)=>$q->where('user_id',$user->id));
                    }

                    $userAnnouncements=$query->latest('published_at')->take(8)->get();
                }
            } catch (\Throwable $e) {
                $userAnnouncements=collect();
            }

            $view->with('userAnnouncements',$userAnnouncements);
        });
    }
}
