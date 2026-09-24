<?php
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class,'home'])->name('home');
Route::get('/studios/{studio}', [PublicController::class,'studio'])->name('studios.show');
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

Route::prefix('admin')->name('admin.')->middleware(['auth','admin'])->group(function(){
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
});
