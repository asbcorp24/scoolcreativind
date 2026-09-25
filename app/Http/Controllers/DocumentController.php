<?php
namespace App\Http\Controllers;

use App\Models\OfficialDocument;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $query=OfficialDocument::where('is_published',true);

        if($request->filled('category')){
            $query->where('category',$request->string('category'));
        }

        return view('documents.index',[
            'documents'=>$query->orderBy('sort_order')->orderByDesc('document_date')->orderByDesc('id')->paginate(20)->withQueryString(),
            'categories'=>OfficialDocument::where('is_published',true)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'activeCategory'=>$request->input('category'),
        ]);
    }
}
