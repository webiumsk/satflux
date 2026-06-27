<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\RegistrationService;
use App\Services\Compliance\ComplianceGate;
use App\Support\Legal\LegalConsent;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;

class RegisterController extends Controller
{
    public function __construct(
        protected RegistrationService $registrationService,
        protected ComplianceGate $complianceGate,
    ) {}

    /**
     * Handle an incoming registration request.
     */
    public function register(Request $request)
    {
        if (config('guest.seed_first_registration')) {
            return response()->json([
                'message' => __('messages.seed_first_registration_required'),
            ], 422);
        }

        $request->validate(array_merge([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], LegalConsent::registrationRules()));

        $this->complianceGate->assertRegistrationAllowed(
            $request,
            $request->input('email'),
            $request->input('name'),
        );

        $user = $this->registrationService->register(
            $request->input('email'),
            $request->input('password')
        );

        $this->complianceGate->linkLatestRegistrationScreening($request->input('email'), $user);

        LegalConsent::recordRegistration($user);

        return response()->json([
            'message' => __('messages.registration_successful'),
            'user' => $user,
        ], 201);
    }
}
