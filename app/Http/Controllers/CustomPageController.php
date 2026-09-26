<?php
namespace App\Http\Controllers;

use App\Models\CustomPage;

class CustomPageController extends Controller
{
    public function show(CustomPage $page)
    {
        abort_unless($page->is_published,404);
        $page->load(['parent','children'=>fn($q)=>$q->where('is_published',true),'media']);
        return view('pages.show',compact('page'));
    }
}
