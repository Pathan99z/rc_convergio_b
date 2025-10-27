<?php

use Laravel\Sanctum\Sanctum;

return [
	// Which domains are considered stateful for SPA auth
	'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
		'%s%s',
		'localhost,localhost:3000,localhost:5173,127.0.0.1,127.0.0.1:8000,::1',
		Sanctum::currentApplicationUrlWithPort(),
	))),

	// Guards Sanctum will try before falling back to bearer token
	'guard' => ['web'],

	// Expiration in minutes for issued tokens (null = use token's own expires_at)
	'expiration' => null,

	// Token prefix for secret scanning
	'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

	// Middleware used by Sanctum when authenticating first-party SPA
	'middleware' => [
		'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
		'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
		'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
	],
];


