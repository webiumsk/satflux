<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentationArticleRequest extends FormRequest
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
        $articleId = $this->route('article')?->id ?? null;
        $supportedLocales = ['en', 'sk', 'es', 'cz', 'de', 'fr', 'hu', 'pl'];

        $rules = [
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('documentation_articles', 'slug')->ignore($articleId),
            ],
            'title' => ['required', 'array'],
            'content' => ['required', 'array'],
            'category_id' => ['nullable', 'exists:documentation_categories,id'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_published' => ['nullable', 'boolean'],
            'meta_description' => ['nullable', 'array'],
        ];

        // Validate each locale in title and content
        foreach ($supportedLocales as $locale) {
            $rules["title.{$locale}"] = ['nullable', 'string', 'max:500'];
            $rules["content.{$locale}"] = ['nullable', 'string'];
            $rules["meta_description.{$locale}"] = ['nullable', 'string', 'max:255'];
        }

        // At least one locale must be provided
        $rules['title'] = array_merge($rules['title'], ['required']);
        $rules['content'] = array_merge($rules['content'], ['required']);

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'This slug is already in use.',
            'title.required' => 'Title is required in at least one language.',
            'content.required' => 'Content is required in at least one language.',
            'category_id.exists' => 'The selected category does not exist.',
        ];
    }
}

