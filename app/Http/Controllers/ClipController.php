<?php
namespace App\Http\Controllers;

use App\Models\Clip;

class ClipController extends Controller
{
    public function index()
    {
        return view('clips.index',[
            'clips'=>Clip::where('is_published',true)
                ->orderBy('sort_order')->orderByDesc('id')->get(),
        ]);
    }

    public function show(Clip $clip)
    {
        abort_unless($clip->is_published,404);

        return view('clips.show',[
            'clip'=>$clip,
            'moreClips'=>Clip::where('is_published',true)
                ->whereKeyNot($clip->id)
                ->orderBy('sort_order')->orderByDesc('id')->take(6)->get(),
        ]);
    }
}
