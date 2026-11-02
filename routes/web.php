<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/debug-storage', function () {
    $dir = storage_path('app/public/products');
    return [
        'dir' => $dir,
        'dir_exists' => is_dir($dir),
        'files' => is_dir($dir) ? array_slice(scandir($dir), 0, 20) : null,
        'target_exists' => file_exists($dir.'/1XK4JX1aCmL1SQAZ6MnB8Pdl2i5s6muGQoPkYgOW.jpg'),
    ];
});

use Illuminate\Support\Facades\Storage;

Route::get('/storage/{path}', function ($path) {
    abort_unless(Storage::disk('public')->exists($path), 404);
    return Storage::disk('public')->response($path);
})->where('path', '.*');
