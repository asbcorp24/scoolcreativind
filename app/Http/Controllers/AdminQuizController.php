<?php
namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Http\Request;

class AdminQuizController extends Controller
{
 public function index(){
  return view('admin.quizzes',[
   'quizzes'=>Quiz::withCount('attempts')->latest()->get(),
   'attempts'=>QuizAttempt::with(['quiz','user'])->latest('completed_at')->take(100)->get(),
  ]);
 }

 public function save(Request $request, ?Quiz $quiz=null){
  $data=$request->validate([
   'title'=>'required|string|max:220',
   'description'=>'nullable|string|max:5000',
   'pass_score'=>'required|integer|min:1|max:100',
   'questions_json'=>'required|json',
   'is_published'=>'nullable|boolean',
  ]);

  $questions=json_decode($data['questions_json'],true);
  if(!is_array($questions) || !count($questions)){
   return back()->withErrors(['questions_json'=>'Добавьте хотя бы один вопрос.'])->withInput();
  }

  foreach($questions as $i=>$q){
   if(empty($q['question']) || empty($q['options']) || !is_array($q['options']) || !array_key_exists('correct',$q)){
    return back()->withErrors(['questions_json'=>'Ошибка в вопросе №'.($i+1).'. Нужны question, options и correct.'])->withInput();
   }
  }

  $quiz ??= new Quiz();
  $quiz->fill([
   'title'=>$data['title'],
   'description'=>$data['description'] ?? null,
   'pass_score'=>$data['pass_score'],
   'questions_json'=>$questions,
   'is_published'=>$request->boolean('is_published'),
  ])->save();

  return back()->with('success','Викторина сохранена.');
 }

 public function delete(Quiz $quiz){
  $quiz->delete();
  return back()->with('success','Викторина удалена.');
 }
}
