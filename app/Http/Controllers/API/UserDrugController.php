<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\UserDrug;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class UserDrugController extends Controller
{
	public $successStatus = 200;
	public $internalServerStatus = 500;
	public $errorStatus = 400;
	public $statusNotFound = 404;
	/**
	 * Add a drug to the authenticated user's list.
	 */
	public function addDrug(Request $request)
	{
		$request->validate([
			'rxcui' => 'required|string'
		]);

		$rxcui = $request->rxcui;

		try {
			$apiUrl = "https://rxnav.nlm.nih.gov/REST/rxcui/{$rxcui}/properties.json";
			$response = Http::get($apiUrl);

			if (!$response->successful() || empty($response['properties']['name'])) {
				return response()->json([
					'status' => false,
					'message' => 'Invalid RXCUI provided'
				], $this->errorStatus);
			}

			$drugName = $response['properties']['name'];

			$exists = UserDrug::where('user_id', Auth::id())
							  ->where('rxcui', $rxcui)
							  ->exists();

			if ($exists) {
				return response()->json([
					'status' => false,
					'message' => 'Drug already exists in your list'
				], $this->errorStatus);
			}

			UserDrug::create([
				'user_id'   => Auth::id(),
				'rxcui'     => $rxcui,
				'drug_name' => $drugName
			]);

			return response()->json([
				'status'  => true,
				'message' => 'Drug added successfully'
			]);
		} catch (Exception $e) {
			Log::error("Add Drug Error: " . $e->getMessage());
			return response()->json([
				'status'  => false,
				'message' => 'An error occurred while adding the drug',
				'error'   => $e->getMessage()
			], $this->internalServerStatus);
		}
	}

	/**
	 * Delete a drug from the user's list.
	 */
	public function deleteDrug($rxcui)
	{
		try {
			$drug = UserDrug::where('user_id', Auth::id())
							->where('rxcui', $rxcui)
							->first();

			if (!$drug) {
				return response()->json([
					'status' => false,
					'message' => 'Drug not found in your list'
				], status: $this->statusNotFound);
			}

			$drug->delete();

			return response()->json([
				'status'  => true,
				'message' => 'Drug deleted successfully'
			]);
		} catch (Exception $e) {
			Log::error("Delete Drug Error: " . $e->getMessage());
			return response()->json([
				'status'  => false,
				'message' => 'An error occurred while deleting the drug',
				'error'   => $e->getMessage()
			], $this->internalServerStatus);
		}
	}

	/**
	 * Get user's drugs with enriched info from RxNorm.
	 */
	public function getUserDrugs()
	{
		try {
			$userDrugs = UserDrug::where('user_id', Auth::id())->get();
			$results = [];

			foreach ($userDrugs as $drug) {
				$rxcui = $drug->rxcui;

				$relatedUrl = "https://rxnav.nlm.nih.gov/REST/rxcui/{$rxcui}/related.json?tty=IN+DF";

				$relatedData = cache()->remember("rxnorm_related_{$rxcui}", now()->addDays(7), function () use ($relatedUrl) {
					$res = Http::get($relatedUrl);
					return $res->successful() ? $res->json() : [];
				});

				$baseNames = [];
				$doseForms = [];

				foreach ($relatedData['relatedGroup']['conceptGroup'] ?? [] as $group) {
					if (($group['tty'] ?? '') === 'IN') {
						foreach ($group['conceptProperties'] ?? [] as $concept) {
							$baseNames[] = $concept['name'];
						}
					}

					if (($group['tty'] ?? '') === 'DF') {
						foreach ($group['conceptProperties'] ?? [] as $concept) {
							$doseForms[] = $concept['name'];
						}
					}
				}

				$results[] = [
					'rxcui'                => $rxcui,
					'drug_name'            => $drug->drug_name,
					'ingredient_base_names'=> array_unique($baseNames),
					'dosage_forms'         => array_unique($doseForms),
				];
			}

			return response()->json([
				'status' => true,
				'medications' => $results
			]);
		} catch (Exception $e) {
			Log::error("Fetch Drugs Error: " . $e->getMessage());
			return response()->json([
				'status'  => false,
				'message' => 'Failed to fetch user drug list',
				'error'   => $e->getMessage()
			], $this->internalServerStatus);
		}
	}
}