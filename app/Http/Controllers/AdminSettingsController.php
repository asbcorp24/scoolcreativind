<?php
namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function edit()
    {
        $settings=SiteSetting::pluck('value','key')->all();
        return view('admin.settings',compact('settings'));
    }

    public function update(Request $request)
    {
        $data=$request->validate([
            'home_eyebrow'=>'nullable|string|max:255',
            'home_title_line1'=>'nullable|string|max:255',
            'home_title_line2'=>'nullable|string|max:255',
            'home_title_line3'=>'nullable|string|max:255',
            'home_intro'=>'nullable|string|max:2000',
            'home_primary_button'=>'nullable|string|max:120',
            'home_secondary_button'=>'nullable|string|max:120',
            'home_studios_eyebrow'=>'nullable|string|max:255',
            'home_studios_title'=>'nullable|string|max:255',
            'home_studios_text'=>'nullable|string|max:2000',
            'home_360_title'=>'nullable|string|max:255',
            'home_360_text'=>'nullable|string|max:2000',
            'home_works_title'=>'nullable|string|max:255',
            'home_works_text'=>'nullable|string|max:2000',
            'home_cta_eyebrow'=>'nullable|string|max:255',
            'home_cta_title'=>'nullable|string|max:500',
            'home_cta_button'=>'nullable|string|max:120',

            'seo_title'=>'nullable|string|max:255',
            'seo_description'=>'nullable|string|max:500',
            'seo_keywords'=>'nullable|string|max:1000',
            'seo_robots'=>'nullable|string|max:120',
            'seo_canonical'=>'nullable|url|max:1000',
            'seo_og_title'=>'nullable|string|max:255',
            'seo_og_description'=>'nullable|string|max:500',
            'seo_og_image'=>'nullable|url|max:2000',
            'seo_twitter_card'=>'nullable|string|max:120',
        ]);

        foreach($data as $key=>$value){
            SiteSetting::setValue($key,$value);
        }

        return back()->with('success','Настройки сайта сохранены.');
    }
}
