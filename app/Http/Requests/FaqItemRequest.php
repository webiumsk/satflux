<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FaqItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $itemId = $this->route('item')?->id ?? null;
        $supportedLocales = ['en', 'sk', 'es', 'cz', 'de', 'fr', 'hu', 'pl'];

        $rules = [
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('faq_items', 'slug')->ignore($itemId),
            ],
            'question' => ['required', 'array'],
            'answer' => ['required', 'array'],
            'category_id' => ['nullable', 'exists:faq_categories,id'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_published' => ['nullable', 'boolean'],
        ];

        // Validate each locale in question and answer
        foreach ($supportedLocales as $locale) {
            $rules["question.{$locale}"] = ['nullable', 'string', 'max:500'];
            $rules["answer.{$locale}"] = ['nullable', 'string'];
        }

        // At least one locale must be provided
        $rules['question'] = array_merge($rules['question'], ['required']);
        $rules['answer'] = array_merge($rules['answer'], ['required']);

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'This slug is already in use.',
            'question.required' => 'Question is required in at least one language.',
            'answer.required' => 'Answer is required in at least one language.',
            'category_id.exists' => 'The selected category does not exist.',
        ];
    }
}

