<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DrugSearchController;  
use App\Http\Controllers\API\UserDrugController;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login'])->name('login');

Route::get('search-drug', [DrugSearchController::class, 'search']);

Route::middleware('auth:api')->group(function () {
	Route::post('/user/drugs', [UserDrugController::class, 'addDrug']);
	Route::delete('/user/drugs/{rxcui}', [UserDrugController::class, 'deleteDrug']);
	Route::get('/user/drugs', [UserDrugController::class, 'getUserDrugs']);
});