<?php
namespace App\Http\Controllers;

use App\Models\MusicTrack;
use Illuminate\Http\Request;

class DesignClipController extends Controller
{
    public function show(Request $request)
    {
        $track=null;

        if($request->filled('track')){
            $track=MusicTrack::where('is_active',true)->find($request->integer('track'));
        }

        if(!$track){
            $track=MusicTrack::where('is_active',true)
                ->where('title','Дизайн начинается с чувства')
                ->first();
        }

        if(!$track){
            $track=MusicTrack::where('is_active',true)
                ->where(function($query){
                    $query->where('title','like','%Дизайн%')
                          ->orWhere('title','like','%чувств%');
                })
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();
        }

        return view('clips.design',compact('track'));
    }
}
