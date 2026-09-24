<?php
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminContentController;
use App\Http\Controllers\AdminPeopleEquipmentController;
use App\Http\Controllers\AdminLearningController;
use App\Http\Controllers\LearningController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class,'home'])->name('home');
Route::get('/studios/{studio}', [PublicController::class,'studio'])->name('studios.show');
Route::get('/team', [PublicController::class,'team'])->name('team');
Route::get('/equipment', [PublicController::class,'equipment'])->name('equipment');
Route::get('/schedule',[LearningController::class,'schedule'])->name('schedule');
Route::get('/portfolio/{profile}',[LearningController::class,'portfolio'])->name('portfolio.show');
Route::get('/my-portfolio',[LearningController::class,'myPortfolio'])->middleware('auth')->name('portfolio.mine');
Route::get('/competitions',[LearningController::class,'competitions'])->name('competitions');
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
    Route::get('/studios/create',[AdminController::class,'studioForm'])->name('studios.create');
    Route::get('/studios/{studio}/edit',[AdminController::class,'studioForm'])->name('studios.edit');
    Route::post('/studios/save/{studio?}',[AdminController::class,'saveStudio'])->name('studios.save');
    Route::delete('/studios/{studio}',[AdminController::class,'deleteStudio'])->name('studios.delete');
    Route::get('/studios/{studio}/media',[AdminController::class,'mediaForm'])->name('media');
    Route::post('/studios/{studio}/media',[AdminController::class,'addMedia'])->name('media.add');
    Route::delete('/media/{media}',[AdminController::class,'deleteMedia'])->name('media.delete');
    Route::get('/news/create',[AdminController::class,'newsForm'])->name('news.create');
    Route::get('/news/{post}/edit',[AdminController::class,'newsForm'])->name('news.edit');
    Route::post('/news/save/{post?}',[AdminController::class,'saveNews'])->name('news.save');
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
    Route::post('/competitions/save/{competition?}',[AdminLearningController::class,'saveCompetition'])->name('competitions.save');
    Route::delete('/competitions/{competition}',[AdminLearningController::class,'deleteCompetition'])->name('competitions.delete');
    Route::post('/achievements/save/{achievement?}',[AdminLearningController::class,'saveAchievement'])->name('achievements.save');
    Route::delete('/achievements/{achievement}',[AdminLearningController::class,'deleteAchievement'])->name('achievements.delete');
    });
});
