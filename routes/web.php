<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
//use Inertia\Inertia;
use App\Models\Job;
use App\Livewire\Counter;



/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::get('/counter', Counter::class);

Route::get('/', function () {
//    $jobs = Job::all();
//    dd($jobs[1]->title);

    return view('home',
        [
            "title" => "Home",
            "greeting" => "Hello, World!"
        ]
    );
});

Route::get('/about', function () {
    return view('about');
});

Route::get('/contact', function () {
    return view('contact');
});

Route::get('/jobs', function ()  {
    return view('jobs', [
        'jobs' => Job::all()
    ]);
});

Route::get('/jobs/{id}', function ($id)  {
    $job = Job::find($id);
    return view('job', ['job' => $job]);
});


// Example tests

Route::get('/string', function () {
    return 'about';
});
Route::get('/array', function () {
    return ['foo' => 'bar'];
});

//Route::get('/', function () {
//    return Inertia::render('Welcome', [
//        'canLogin' => Route::has('login'),
//        'canRegister' => Route::has('register'),
//        'laravelVersion' => Application::VERSION,
//        'phpVersion' => PHP_VERSION,
//    ]);
//});


//
//Route::get('/dashboard', function () {
//    return Inertia::render('Dashboard');
//})->middleware(['auth', 'verified'])->name('dashboard');
//
//Route::middleware('auth')->group(function () {
//    Route::get('/about', fn () => Inertia::render('About'))->name('about');
//
//    Route::get('users', [UserController::class, 'index'])->name('users.index');
//
//    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
//    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
//});
//
//require __DIR__.'/auth.php';
