<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LocaleController extends Controller
{
    /**
     * Supported locales
     */
    private const SUPPORTED_LOCALES = [
        'en' => 'English',
        'cz' => 'Čeština',
        'de' => 'Deutsch',
        'es' => 'Español',
        'fr' => 'Français',
        'hu' => 'Magyar',
        'pl' => 'Polski',
        'sk' => 'Slovenčina',
    ];

    /**
     * Get available locales
     */
    public function index()
    {
        return response()->json([
            'data' => array_map(function ($code, $name) {
                return [
                    'code' => $code,
                    'name' => $name,
                ];
            }, array_keys(self::SUPPORTED_LOCALES), self::SUPPORTED_LOCALES),
            'current' => app()->getLocale(),
        ]);
    }

    /**
     * Set locale for the current session
     */
    public function setLocale(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'locale' => ['required', 'string', 'in:' . implode(',', array_keys(self::SUPPORTED_LOCALES))],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => __('validation.invalid', ['attribute' => 'locale']),
                'errors' => $validator->errors(),
            ], 422);
        }

        $locale = $request->input('locale');
        
        // Store locale in session
        $request->session()->put('locale', $locale);
        
        // Set application locale
        app()->setLocale($locale);

        return response()->json([
            'message' => __('messages.locale_set', ['locale' => self::SUPPORTED_LOCALES[$locale] ?? $locale]),
            'locale' => $locale,
        ]);
    }
}

