<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Services\Invoicing\ZapriteMigrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZapriteMigrationController extends Controller
{
    public function __invoke(Request $request, ZapriteMigrationService $service): JsonResponse
    {
        $apiKey = $request->input('api_key');
        $page = $request->input('page', 1);
        $unexpectedFields = array_diff(array_keys($request->all()), ['api_key', 'page']);
        $jsonOnly = $request->isJson() && $request->query->count() === 0;

        // Always return JSON, including requests with Accept: text/html. In
        // particular, do not flash a credential in a validation redirect.
        $request->request->remove('api_key');
        $request->query->remove('api_key');
        $request->json()->remove('api_key');

        if (! $jsonOnly || ! is_string($apiKey) || ! preg_match('/\A[\x21-\x7e]{8,512}\z/D', $apiKey)
            || ! is_int($page) || $page < 1 || $page > 20 || $unexpectedFields !== []) {
            return $this->response(['code' => 'zaprite_invalid_input'], 422);
        }

        $result = $service->fetchPage($apiKey, $page);

        return $this->response($result['body'], $result['status']);
    }

    /** @param array<string, mixed> $body */
    private function response(array $body, int $status): JsonResponse
    {
        return response()->json($body, $status, [
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
        ]);
    }
}
