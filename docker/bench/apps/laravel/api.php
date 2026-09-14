<?php

use App\Models\Country;
use Illuminate\Support\Facades\Route;

// `make benchmark-http`: the stateless `api` group, as a JSON API is served.
Route::get('/ping', static fn () => response('pong', 200, ['Content-Type' => 'text/plain']));
Route::get('/json', static fn () => response()->json(['hello' => 'world']));

// One row read by its primary key through the ORM, as OZone and Symfony read theirs.
Route::get('/db', static function () {
	$country = Country::findOrFail('BJ');

	return response()->json([
		'cc2'          => $country->cc2,
		'name'         => $country->name,
		'calling_code' => $country->calling_code,
	]);
});
