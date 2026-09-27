<?php
namespace App\Http\Controllers;

use App\Models\MediaLibraryItem;
use App\Models\Studio;
use App\Models\PortfolioItem;
use App\Models\NewsPost;
use App\Models\CustomPage;
use Illuminate\Http\Request;

class AdminMediaLibraryController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->is_admin,403);

        $query=MediaLibraryItem::with('attachable')->latest();

        if($request->filled('type')){
            $query->where('type',$request->input('type'));
        }

        if($request->filled('section')){
            $map=[
                'studios'=>Studio::class,
                'works'=>PortfolioItem::class,
                'news'=>NewsPost::class,
                'pages'=>CustomPage::class,
            ];
            if(isset($map[$request->input('section')])){
                $query->where('attachable_type',$map[$request->input('section')]);
            }
        }

        if($request->filled('q')){
            $q=trim((string)$request->input('q'));
            $query->where(function($builder) use($q){
                $builder->where('title','like','%'.$q.'%')
                    ->orWhere('caption','like','%'.$q.'%')
                    ->orWhere('file_name','like','%'.$q.'%');
            });
        }

        return view('admin.media-library',[
            'items'=>$query->paginate(40)->withQueryString(),
        ]);
    }
}
