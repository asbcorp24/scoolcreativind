<?php
namespace App\Providers;

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
    }
}
