<?php

declare(strict_types=1);

use App\Modules\Assets\Application\Services\AssetStreamService;
use App\Modules\Assets\Presentation\Http\Controllers\Web\MediaStreamController;
use Illuminate\Support\Facades\Route;

// Deliberately outside /api and deliberately unauthenticated: Instagram's publishing API is
// pull-based, so Meta's fetcher — which carries no bearer token — has to reach the bytes.
// The signed, single-use, 24-hour token is what stands in for auth, and the response streams
// straight from Drive so nothing is ever written to this machine.
Route::get('/media/{token}', MediaStreamController::class)->name(AssetStreamService::ROUTE_NAME);

Route::view('/{any?}', 'app')->where('any', '^(?!api|up|media).*$');
