<?php
namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class EnsureApiAuthenticated extends Middleware
{
	protected function redirectTo($request): ?string
	{
		if ($request->expectsJson()) {
			return null;
		}
		return null;
	}
}