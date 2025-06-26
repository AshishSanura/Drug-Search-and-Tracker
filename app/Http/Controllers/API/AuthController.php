<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Exception;

class AuthController extends Controller
{
	public $successStatus = 200;
	public $internalServerStatus = 500;
	public $errorStatus = 400;
	
	/**
	 * Register a new user.
	 */
	public function register(Request $request)
	{
		try {
			$validator = Validator::make($request->all(), [
				'name'     => 'required|string|max:255',
				'email'    => 'required|string|email|max:255|unique:users',
				'password' => 'required|string|min:6',
			]);

			if ($validator->fails()) {
				return response()->json([
					'status'  => false,
					'message' => 'Validation Errors',
					'errors'  => $validator->errors()
				], status: $this->errorStatus);
			}

			$user = User::create([
				'name'     => $request->name,
				'email'    => $request->email,
				'password' => Hash::make($request->password),
			]);

			$token = $user->createToken('API Token')->accessToken;

			return response()->json([
				'status'  => true,
				'message' => 'User registered successfully',
				'data'    => [
					'user'  => $user->only(['id', 'name', 'email']),
					'token' => $token,
				]
			], $this->successStatus);
		} catch (Exception $e) {
			return response()->json([
				'status'  => false,
				'message' => 'Registration failed',
				'error'   => $e->getMessage(),
			], $this->internalServerStatus);
		}
	}

	/**
	 * Login existing user.
	 */
	public function login(Request $request)
	{
		try {
			$validator = Validator::make($request->all(), [
				'email'    => 'required|string|email',
				'password' => 'required|string|min:6',
			]);

			if ($validator->fails()) {
				return response()->json([
					'status'  => false,
					'message' => 'Validation Errors',
					'errors'  => $validator->errors()
				], $this->errorStatus);
			}

			if (!Auth::attempt($request->only('email', 'password'))) {
				return response()->json([
					'status'  => false,
					'message' => 'Invalid credentials',
				], 401);
			}

			$user = Auth::user();
			$token = $user->createToken('API Token')->accessToken;

			return response()->json([
				'status'  => true,
				'message' => 'Login successful',
				'data'    => [
					'user'  => $user->only(['id', 'name', 'email']),
					'token' => $token,
				]
			], $this->successStatus);
		} catch (Exception $e) {
			return response()->json([
				'status'  => false,
				'message' => 'Login failed',
				'error'   => $e->getMessage(),
			], $this->internalServerStatus);
		}
	}
}