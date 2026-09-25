<?php
namespace App\Providers;

use App\Models\SiteSetting;
use App\Models\MusicTrack;
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
    }
}
