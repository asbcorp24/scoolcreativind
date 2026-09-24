<?php
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminContentController;
use App\Http\Controllers\AdminPeopleEquipmentController;
use App\Http\Controllers\AdminLearningController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\LearningController;
use App\Http\Controllers\AcademicController;
use App\Http\Controllers\AdminAcademicController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\AdminQuizController;
use App\Http\Controllers\CompetitionParticipationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class,'home'])->name('home');
Route::get('/sitemap.xml',[SeoController::class,'sitemap'])->name('sitemap');
Route::get('/robots.txt',[SeoController::class,'robots'])->name('robots');
Route::get('/studios/{studio}', [PublicController::class,'studio'])->name('studios.show');
Route::get('/team', [PublicController::class,'team'])->name('team');
Route::get('/equipment', [PublicController::class,'equipment'])->name('equipment');
Route::get('/schedule',[LearningController::class,'schedule'])->name('schedule');
Route::get('/projects',[LearningController::class,'projects'])->name('projects.index');
Route::get('/portfolio/{profile}',[LearningController::class,'portfolio'])->name('portfolio.show');
Route::get('/my-portfolio',[LearningController::class,'myPortfolio'])->middleware('auth')->name('portfolio.mine');
Route::middleware('auth')->group(function(){
    Route::post('/quizzes/{quiz}',[QuizController::class,'submit'])->name('quizzes.submit');
    Route::get('/quiz-results/{attempt}',[QuizController::class,'result'])->name('quizzes.result');
    Route::post('/competitions/{competition}/register',[CompetitionParticipationController::class,'register'])->name('competitions.register');
    Route::post('/competitions/{competition}/submit',[CompetitionParticipationController::class,'submit'])->name('competitions.submit');
    Route::post('/competitions/{competition}/documents/{documentKey}',[CompetitionParticipationController::class,'uploadDocument'])->name('competitions.documents.upload');
    Route::delete('/competitions/{competition}/documents/{documentKey}',[CompetitionParticipationController::class,'deleteDocument'])->name('competitions.documents.delete');
    Route::delete('/competitions/{competition}/register',[CompetitionParticipationController::class,'cancel'])->name('competitions.cancel');
    Route::get('/study',[AcademicController::class,'dashboard'])->name('academic.dashboard');
    Route::get('/study/homework/{assignment}',[AcademicController::class,'homework'])->name('academic.homework');
    Route::post('/study/homework/{assignment}',[AcademicController::class,'submitHomework'])->name('academic.homework.submit');
});
Route::get('/competitions',[LearningController::class,'competitions'])->name('competitions');
Route::get('/quizzes',[QuizController::class,'index'])->name('quizzes.index');
Route::get('/quizzes/{quiz}',[QuizController::class,'show'])->name('quizzes.show');
Route::get('/certificates/{code}',[QuizController::class,'certificate'])->name('quizzes.certificate');
Route::get('/news', [PublicController::class,'news'])->name('news.index');
Route::get('/news/{post}', [PublicController::class,'newsShow'])->name('news.show');
Route::get('/apply', [PublicController::class,'apply'])->name('apply');
Route::post('/apply', [PublicController::class,'storeApplication'])->name('apply.store');

Route::middleware('guest')->group(function(){
    Route::get('/login',[AuthController::class,'loginForm'])->name('login');
    Route::post('/login',[AuthController::class,'login']);
    Route::get('/register',[AuthController::class,'registerForm'])->name('register');
    Route::post('/register',[AuthController::class,'register']);
});
Route::post('/logout',[AuthController::class,'logout'])->middleware('auth')->name('logout');
Route::get('/cabinet',[PublicController::class,'cabinet'])->middleware('auth')->name('cabinet');

Route::prefix('admin')->name('admin.')->group(function(){
    Route::get('/login',[AdminAuthController::class,'loginForm'])->name('login');
    Route::post('/login',[AdminAuthController::class,'login'])->name('login.submit');
    Route::post('/logout',[AdminAuthController::class,'logout'])->name('logout');

    Route::middleware('admin')->group(function(){
    Route::get('/',[AdminController::class,'dashboard'])->name('dashboard');
    Route::get('/settings',[AdminSettingsController::class,'edit'])->name('settings');
    Route::get('/groups',[AdminAcademicController::class,'groups'])->name('groups');
    Route::post('/groups/save/{group?}',[AdminAcademicController::class,'saveGroup'])->name('groups.save');
    Route::post('/groups/{group}/students/create',[AdminAcademicController::class,'createStudent'])->name('groups.students.create');
    Route::post('/groups/{group}/members',[AdminAcademicController::class,'addMember'])->name('groups.members.add');
    Route::delete('/groups/{group}/members/{user}/{role}',[AdminAcademicController::class,'removeMember'])->name('groups.members.remove');
    Route::get('/subjects',[AdminAcademicController::class,'subjects'])->name('subjects');
    Route::post('/subjects/save/{subject?}',[AdminAcademicController::class,'saveSubject'])->name('subjects.save');
    Route::post('/groups/{group}/subjects',[AdminAcademicController::class,'attachSubject'])->name('groups.subjects.attach');
    Route::get('/journal',[AdminAcademicController::class,'journal'])->name('journal');
    Route::post('/journal/lessons',[AdminAcademicController::class,'createJournalLesson'])->name('journal.lessons.create');
    Route::patch('/journal/entries/{entry}',[AdminAcademicController::class,'saveJournalEntry'])->name('journal.entries.update');
    Route::get('/homework',[AdminAcademicController::class,'homework'])->name('homework');
    Route::post('/homework/save/{assignment?}',[AdminAcademicController::class,'saveHomework'])->name('homework.save');
    Route::get('/homework/{assignment}/submissions',[AdminAcademicController::class,'submissions'])->name('homework.submissions');
    Route::patch('/homework/submissions/{submission}',[AdminAcademicController::class,'reviewSubmission'])->name('homework.submissions.review');
    Route::post('/settings',[AdminSettingsController::class,'update'])->name('settings.update');
    Route::get('/studios/create',[AdminController::class,'studioForm'])->name('studios.create');
    Route::get('/studios/{studio}/edit',[AdminController::class,'studioForm'])->name('studios.edit');
    Route::post('/studios/save/{studio?}',[AdminController::class,'saveStudio'])->name('studios.save');
    Route::delete('/studios/{studio}',[AdminController::class,'deleteStudio'])->name('studios.delete');
    Route::get('/studios/{studio}/media',[AdminController::class,'mediaForm'])->name('media');
    Route::post('/studios/{studio}/media',[AdminController::class,'addMedia'])->name('media.add');
    Route::patch('/media/{media}/flags',[AdminController::class,'updateMediaFlags'])->name('media.flags');
    Route::delete('/media/{media}',[AdminController::class,'deleteMedia'])->name('media.delete');
    Route::get('/news/create',[AdminController::class,'newsForm'])->name('news.create');
    Route::get('/news/{post}/edit',[AdminController::class,'newsForm'])->name('news.edit');
    Route::post('/news/save/{post?}',[AdminController::class,'saveNews'])->name('news.save');
    Route::post('/applications/{application}/enroll',[AdminController::class,'enrollApplication'])->name('applications.enroll');
    Route::patch('/applications/{application}',[AdminController::class,'applicationStatus'])->name('applications.status');

    Route::get('/projects',[AdminContentController::class,'projects'])->name('projects');
    Route::post('/projects/save/{project?}',[AdminContentController::class,'saveProject'])->name('projects.save');
    Route::delete('/projects/{project}',[AdminContentController::class,'deleteProject'])->name('projects.delete');

    Route::get('/events',[AdminContentController::class,'events'])->name('events');
    Route::post('/events/save/{event?}',[AdminContentController::class,'saveEvent'])->name('events.save');
    Route::delete('/events/{event}',[AdminContentController::class,'deleteEvent'])->name('events.delete');

    Route::get('/team',[AdminPeopleEquipmentController::class,'team'])->name('team');
    Route::post('/team/save/{member?}',[AdminPeopleEquipmentController::class,'saveTeam'])->name('team.save');
    Route::delete('/team/{member}',[AdminPeopleEquipmentController::class,'deleteTeam'])->name('team.delete');

    Route::get('/equipment',[AdminPeopleEquipmentController::class,'equipment'])->name('equipment');
    Route::post('/equipment/save/{item?}',[AdminPeopleEquipmentController::class,'saveEquipment'])->name('equipment.save');
    Route::delete('/equipment/{item}',[AdminPeopleEquipmentController::class,'deleteEquipment'])->name('equipment.delete');

    Route::get('/schedule',[AdminLearningController::class,'schedule'])->name('schedule');
    Route::post('/schedule/save/{lesson?}',[AdminLearningController::class,'saveLesson'])->name('schedule.save');
    Route::delete('/schedule/{lesson}',[AdminLearningController::class,'deleteLesson'])->name('schedule.delete');

    Route::get('/students',[AdminLearningController::class,'students'])->name('students');
    Route::post('/students/save/{profile?}',[AdminLearningController::class,'saveStudent'])->name('students.save');
    Route::get('/students/{profile}/portfolio',[AdminLearningController::class,'portfolio'])->name('students.portfolio');
    Route::post('/students/{profile}/portfolio/save/{item?}',[AdminLearningController::class,'savePortfolio'])->name('students.portfolio.save');
    Route::delete('/portfolio/{item}',[AdminLearningController::class,'deletePortfolio'])->name('portfolio.delete');

    Route::get('/competitions',[AdminLearningController::class,'competitions'])->name('competitions');
    Route::get('/quizzes',[AdminQuizController::class,'index'])->name('quizzes');
    Route::post('/quizzes/save/{quiz?}',[AdminQuizController::class,'save'])->name('quizzes.save');
    Route::delete('/quizzes/{quiz}',[AdminQuizController::class,'delete'])->name('quizzes.delete');
    Route::post('/competitions/save/{competition?}',[AdminLearningController::class,'saveCompetition'])->name('competitions.save');
    Route::delete('/competitions/{competition}',[AdminLearningController::class,'deleteCompetition'])->name('competitions.delete');
    Route::post('/achievements/save/{achievement?}',[AdminLearningController::class,'saveAchievement'])->name('achievements.save');
    Route::delete('/achievements/{achievement}',[AdminLearningController::class,'deleteAchievement'])->name('achievements.delete');
    });
});
