<?php
namespace App\Http\Controllers;

use App\Models\SiteSetting;

class ContactController extends Controller
{
    public function index()
    {
        $settings=SiteSetting::pluck('value','key')->all();
        return view('contacts.index',compact('settings'));
    }
}
