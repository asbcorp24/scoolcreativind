<?php
namespace App\Http\Controllers;

use App\Models\Question;
use Illuminate\Http\Request;

class AdminQuestionController extends Controller
{
    public function index(Request $request)
    {
        $query=Question::latest();

        if($request->filled('status')){
            $query->where('status',$request->string('status'));
        }

        return view('admin.questions',[
            'questions'=>$query->paginate(30)->withQueryString(),
            'activeStatus'=>$request->input('status'),
        ]);
    }

    public function update(Request $request, Question $question)
    {
        $data=$request->validate([
            'status'=>'required|in:new,processing,answered,closed',
            'answer'=>'nullable|string|max:20000',
        ]);

        if($data['status']==='answered' && blank($data['answer'] ?? null)){
            return back()->withErrors(['answer'=>'Для статуса «Отвечено» укажите текст ответа.']);
        }

        $data['answered_at']=$data['status']==='answered' ? now() : $question->answered_at;
        $question->update($data);

        return back()->with('success','Обращение обновлено.');
    }

    public function delete(Question $question)
    {
        $question->delete();
        return back()->with('success','Обращение удалено.');
    }
}
