<?php
namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class QuizController extends Controller
{
 public function index(){
  return view('quizzes.index',[
   'quizzes'=>Quiz::where('is_published',true)->latest()->get(),
   'attempts'=>auth()->check()
      ? QuizAttempt::where('user_id',auth()->id())->latest('completed_at')->get()->keyBy('quiz_id')
      : collect(),
  ]);
 }

 public function show(Quiz $quiz){
  abort_unless($quiz->is_published,404);
  return view('quizzes.show',compact('quiz'));
 }

 public function submit(Request $request, Quiz $quiz){
  abort_unless($quiz->is_published,404);
  $questions=$quiz->questions_json ?: [];
  abort_if(!count($questions),422,'В викторине нет вопросов.');

  $answers=$request->input('answers',[]);
  $correct=0;
  foreach($questions as $i=>$q){
   $expected=(string)($q['correct'] ?? '');
   $given=(string)($answers[$i] ?? '');
   if($expected!=='' && hash_equals($expected,$given)) $correct++;
  }

  $score=(int)round(($correct/count($questions))*100);
  $passed=$score >= $quiz->pass_score;

  $attempt=QuizAttempt::create([
   'quiz_id'=>$quiz->id,
   'user_id'=>auth()->id(),
   'score'=>$score,
   'passed'=>$passed,
   'answers_json'=>$answers,
   'certificate_code'=>$passed ? strtoupper(Str::random(12)) : null,
   'completed_at'=>now(),
  ]);

  return redirect()->route('quizzes.result',$attempt);
 }

 public function result(QuizAttempt $attempt){
  abort_unless(auth()->check() && ($attempt->user_id===auth()->id() || auth()->user()->is_admin),403);
  $attempt->load(['quiz','user']);
  return view('quizzes.result',compact('attempt'));
 }

 public function certificate(string $code){
  $attempt=QuizAttempt::where('certificate_code',$code)->where('passed',true)->with(['quiz','user'])->firstOrFail();
  return view('quizzes.certificate',compact('attempt'));
 }
}
