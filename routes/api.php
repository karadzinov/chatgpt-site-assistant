<?php

use Illuminate\Support\Facades\Route;
use MartinK\ChatGptSiteAssistant\Http\Controllers\ChatGptController;

Route::post('chat', [ChatGptController::class, 'chat']);

