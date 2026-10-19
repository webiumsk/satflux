<?php

namespace App\Http\Controllers;

use App\Exceptions\EmailCodeChallengeException;
use App\Models\Store;
use App\Models\User;
use App\Services\Auth\SensitiveActionAuthorization;
use App\Services\CashuChangeRecoveryService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class CashuChangeRecoveryController extends Controller
{
    public function __construct(protected CashuChangeRecoveryService $recovery) {}

    public function summary(Request $request, Store $store): JsonResponse
    {
        return $this->respond(fn () => response()->json(['data' => $this->recovery->summary($store, $this->actor($request))]));
    }

    public function requestConfirmation(Request $request, Store $store): JsonResponse
    {
        return $this->respond(function () use ($request, $store) {
            $user = $this->actor($request);
            $this->recovery->assertOwner($store, $user);
            $validated = $request->validate(['action' => ['required', Rule::in(['export', 'verify'])], 'password' => ['nullable', 'string']]);
            SensitiveActionAuthorization::assertAllowed($user, $request);

            return response()->json(['data' => $this->recovery->requestConfirmation($store, $user, $validated['action'])]);
        });
    }

    public function resendConfirmation(Request $request, Store $store): JsonResponse
    {
        return $this->respond(function () use ($request, $store) {
            $user = $this->actor($request);
            $this->recovery->assertOwner($store, $user);
            $validated = $request->validate(['action' => ['required', Rule::in(['export', 'verify'])]]);

            return response()->json(['data' => $this->recovery->resendConfirmation($store, $user, $validated['action'])]);
        });
    }

    public function export(Request $request, Store $store): JsonResponse
    {
        return $this->perform($request, $store, 'export');
    }

    public function verify(Request $request, Store $store): JsonResponse
    {
        return $this->perform($request, $store, 'verify');
    }

    private function perform(Request $request, Store $store, string $action): JsonResponse
    {
        return $this->respond(function () use ($request, $store, $action) {
            $user = $this->actor($request);
            $this->recovery->assertOwner($store, $user);
            $validated = $request->validate([
                'code' => ['required', 'string', 'regex:/^[0-9]{6}$/D'],
                'confirmed' => ['required', static function ($attribute, $value, $fail) {
                    if ($value !== true) {
                        $fail('Explicit confirmation is required.');
                    }
                }],
            ]);
            $result = $this->recovery->perform($store, $user, $action, $validated['code']);

            return $action === 'export'
                ? response()->json($result)->header('Content-Disposition', 'attachment; filename="cashumelt-change.json"')
                : response()->json(['data' => $result]);
        });
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /** Keep errors fixed and never reflect provider replies, bearer tokens or transport details. */
    private function respond(\Closure $operation): JsonResponse
    {
        try {
            $response = $operation();
        } catch (EmailCodeChallengeException $e) {
            $response = $e->render();
        } catch (ValidationException $e) {
            $response = response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (HttpResponseException $e) {
            $response = $e->getResponse();
        } catch (HttpExceptionInterface $e) {
            $response = response()->json(['code' => 'cashu_change_owner_required', 'message' => 'The verified store owner must authorize change recovery.'], $e->getStatusCode());
        } catch (\Throwable) {
            $response = response()->json(['code' => 'cashu_change_unavailable', 'message' => 'Cashu change recovery is unavailable.'], 502);
        }

        if (! $response instanceof JsonResponse) {
            $response = response()->json(['code' => 'cashu_change_unavailable', 'message' => 'Cashu change recovery is unavailable.'], 502);
        }

        return $response->withHeaders(CashuChangeRecoveryService::HEADERS);
    }
}
