<?php
namespace App\Http\Controllers;

use App\Models\Question;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function create()
    {
        return view('questions.create');
    }

    public function store(Request $request)
    {
        $data=$request->validate([
            'name'=>'required|string|max:180',
            'email'=>'nullable|email|max:255',
            'phone'=>'nullable|string|max:80',
            'subject'=>'nullable|string|max:255',
            'question'=>'required|string|min:10|max:10000',
            'consent'=>'accepted',
        ],[
            'consent.accepted'=>'Необходимо согласие на обработку персональных данных.',
        ]);

        unset($data['consent']);
        $data['user_id']=auth()->id();

        Question::create($data);

        return redirect()->route('questions.create')->with('success','Ваш вопрос отправлен.');
    }
}
