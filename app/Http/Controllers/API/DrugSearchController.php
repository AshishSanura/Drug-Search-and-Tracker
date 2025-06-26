<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DrugSearchController extends Controller
{
	public $internalServerStatus = 500;
	public $errorStatus = 400;
	public function search(Request $request)
	{
		$request->validate([
			'drug_name' => 'required|string|max:255'
		]);

		$drugName = $request->query('drug_name');

		try {
			// Rate limiting: 10 requests per minute per drug per IP
			$key = 'drug-search:' . Str::slug($drugName) . ':' . $request->ip();
			if (RateLimiter::tooManyAttempts($key, 10)) {
				return response()->json([
					'status'       => false,
					'message'      => 'Too many requests. Please try again later.',
					'retry_after'  => RateLimiter::availableIn($key)
				], $this->errorStatus);
			}
			RateLimiter::hit($key, 60);

			
			$drugResponse = Http::timeout(10)->get("https://rxnav.nlm.nih.gov/REST/drugs.json", [
				'name' => $drugName
			]);

			if (!$drugResponse->successful()) {
				return response()->json([
					'status'  => false,
					'message' => 'Unable to fetch drug data'
				], $this->internalServerStatus);
			}

			$drugsData = $drugResponse->json();
			$conceptGroups = $drugsData['drugGroup']['conceptGroup'] ?? [];

			
			$results = [];
			foreach ($conceptGroups as $group) {
				if (($group['tty'] ?? '') === 'SBD' && isset($group['conceptProperties'])) {
					foreach ($group['conceptProperties'] as $drug) {
						if (count($results) >= 5) break 2;

						$rxcui = $drug['rxcui'];
						$name  = $drug['name'];

						
						$relatedUrl = "https://rxnav.nlm.nih.gov/REST/rxcui/{$rxcui}/related.json?tty=IN+DF";

						$relatedData = Cache::remember("rxnorm_related_{$rxcui}", now()->addDays(7), function () use ($relatedUrl) {
							$res = Http::timeout(seconds: 10)->get($relatedUrl);
							return $res->successful() ? $res->json() : [];
						});

						$ingredientBaseNames = [];
						$dosageForms = [];

						foreach ($relatedData['relatedGroup']['conceptGroup'] ?? [] as $relGroup) {
							if (($relGroup['tty'] ?? '') === 'IN') {
								foreach ($relGroup['conceptProperties'] ?? [] as $concept) {
									$ingredientBaseNames[] = $concept['name'];
								}
							}

							if (($relGroup['tty'] ?? '') === 'DF') {
								foreach ($relGroup['conceptProperties'] ?? [] as $concept) {
									$dosageForms[] = $concept['name'];
								}
							}
						}

						$results[] = [
							'rxcui'                 => $rxcui,
							'name'                  => $name,
							'ingredient_base_names' => array_values(array_unique($ingredientBaseNames)),
							'dosage_forms'          => array_values(array_unique($dosageForms)),
						];
					}
				}
			}

			return response()->json([
				'status'      => true,
				'search_term' => $drugName,
				'results'     => $results
			]);
		} catch (\Exception $e) {
			Log::error("Drug search error: " . $e->getMessage());
			return response()->json([
				'status'  => false,
				'message' => 'An error occurred during the search',
				'error'   => $e->getMessage()
			], $this->internalServerStatus);
		}
	}
}
