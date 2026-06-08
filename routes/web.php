<?php

use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => false,
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        $user = request()->user();

        abort_unless($user, 403);

        return redirect()->route($user->postLoginRouteName());
    })->name('dashboard');

    Route::get('/admin/dashboard', function () {
        return Inertia::render('Admin/Dashboard');
    })->middleware('role:admin')->name('admin.dashboard');

    Route::get('/student/dashboard', function () {
        $user = request()->user();

        if ($user && ! $user->hasCompletedStudentProfile()) {
            return redirect()->route('profile.edit');
        }

        return Inertia::render('Student/Dashboard');
    })->middleware('role:student')->name('student.dashboard');

    Route::middleware('role:student')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/students', [StudentController::class, 'index'])->name('students.index');
        Route::get('/students/{student}', [StudentController::class, 'edit'])->name('students.edit');
        Route::patch('/students/{student}', [StudentController::class, 'update'])->name('students.update');
    });
});

require __DIR__.'/auth.php';
