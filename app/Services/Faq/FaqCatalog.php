<?php

namespace App\Services\Faq;

use RuntimeException;

class FaqCatalog
{
    public const LOCALES = ['en', 'sk', 'es', 'de', 'cs'];

    /** Validate reviewed content and source fingerprints without accessing the database. */
    public function load(?string $root = null): array
    {
        $root ??= base_path();
        $catalog = $this->readJson($root.'/docs/faq/catalog.json');
        $lock = $this->readJson($root.'/docs/faq/sources.lock.json');
        if (($catalog['version'] ?? null) !== 1 || ! is_array($catalog['categories'] ?? null) || ! is_array($catalog['items'] ?? null)
            || ! array_is_list($catalog['categories']) || ! array_is_list($catalog['items']) || $catalog['categories'] === [] || $catalog['items'] === []) {
            throw new RuntimeException('Invalid FAQ catalog structure.');
        }

        $categories = [];
        foreach ($catalog['categories'] as $index => $category) {
            $catalog['categories'][$index]['order'] = $this->entry($category, ['name', 'description']);
            if (isset($categories[$category['slug']])) {
                throw new RuntimeException('Duplicate FAQ category slug.');
            }
            $categories[$category['slug']] = true;
        }

        $slugs = [];
        $sources = [];
        foreach ($catalog['items'] as $index => $item) {
            $catalog['items'][$index]['order'] = $this->entry($item, ['question', 'answer']);
            if (isset($slugs[$item['slug']]) || ! is_string($item['category'] ?? null) || ! isset($categories[$item['category']])) {
                throw new RuntimeException('Duplicate FAQ item or unknown category.');
            }
            $slugs[$item['slug']] = true;
            if (! is_array($item['sources'] ?? null) || ! array_is_list($item['sources']) || $item['sources'] === []) {
                throw new RuntimeException('FAQ item must declare documentation sources.');
            }
            if (count(array_unique($item['sources'], SORT_REGULAR)) !== count($item['sources'])) {
                throw new RuntimeException('FAQ documentation sources must be unique.');
            }
            $hasEnglishSource = false;
            foreach ($item['sources'] as $path) {
                if (! is_string($path) || ! preg_match('~^docs/user/(en|sk|es|de|cs)/[a-z0-9]+(?:-[a-z0-9]+)*\.md$~D', $path)) {
                    throw new RuntimeException('Invalid FAQ documentation source path.');
                }
                $hasEnglishSource = $hasEnglishSource || str_starts_with($path, 'docs/user/en/');
                $this->source($root, $path, $lock);
                $sources[$path] = true;
            }
            if (! $hasEnglishSource) {
                throw new RuntimeException('FAQ item requires a canonical English source.');
            }
            $this->translatedSources($root, $item['sources']);
            foreach (self::LOCALES as $locale) {
                if (mb_strlen($item['question'][$locale]) > 500) {
                    throw new RuntimeException('FAQ question exceeds 500 characters.');
                }
                $answer = $item['answer'][$locale];
                preg_match_all('~\[([^\]\r\n]+)\]\(([^)\s]+)\)~u', $answer, $links, PREG_SET_ORDER);
                if ($links === []) {
                    throw new RuntimeException('Each FAQ answer must link to documentation.');
                }
                foreach ($links as $link) {
                    if (! preg_match('~^/documentation/([a-z0-9]+(?:-[a-z0-9]+)*)$~D', $link[2], $match)
                        || ! in_array('docs/user/en/'.$match[1].'.md', $item['sources'], true)) {
                        throw new RuntimeException('FAQ link must reference a declared English documentation source.');
                    }
                }
                $plain = preg_replace('~\[([^\]\r\n]+)\]\(([^)\s]+)\)~u', '$1', $answer);
                if (str_contains($plain, '](') || str_contains($plain, '<') || str_contains($plain, '>') || preg_match('~(?:https?:|javascript:|data:|\]\[)~i', $plain)) {
                    throw new RuntimeException('FAQ answer contains unsupported link or HTML markup.');
                }
            }
        }
        if (array_diff(array_keys($lock), array_keys($sources)) !== [] || array_diff(array_keys($sources), array_keys($lock)) !== []) {
            throw new RuntimeException('FAQ source lock must exactly cover declared sources.');
        }

        return $catalog;
    }

    /** Validate one record and normalize finite whole JSON order numbers to stable database integers. */
    private function entry(mixed $entry, array $fields): int
    {
        $order = is_array($entry) ? ($entry['order'] ?? null) : null;
        if (! is_array($entry) || ! is_string($entry['slug'] ?? null) || strlen($entry['slug']) > 255
            || ! preg_match('~^[a-z0-9]+(?:-[a-z0-9]+)*$~D', $entry['slug'])
            || (! is_int($order) && ! is_float($order)) || ! is_finite((float) $order)
            || floor((float) $order) !== (float) $order || $order < 0 || $order > 2147483647) {
            throw new RuntimeException('Invalid FAQ slug or ordering.');
        }
        foreach ($fields as $field) {
            $value = $entry[$field] ?? null;
            if (! is_array($value) || array_diff(self::LOCALES, array_keys($value)) !== [] || array_diff(array_keys($value), self::LOCALES) !== []) {
                throw new RuntimeException('FAQ translations must cover exactly EN/SK/ES/DE/CS.');
            }
            foreach ($value as $text) {
                if (! is_string($text) || trim($text) === '') {
                    throw new RuntimeException('FAQ translation cannot be empty.');
                }
            }
        }

        return (int) $order;
    }

    /** Resolve only repository documentation paths and require their reviewed bytes. */
    private function source(string $root, string $path, array $lock): void
    {
        $realRoot = realpath($root.'/docs/user');
        $realPath = realpath($root.'/'.$path);
        if ($realRoot === false || $realPath === false || ! str_starts_with($realPath, $realRoot.DIRECTORY_SEPARATOR)
            || ! is_file($realPath) || ! is_string($lock[$path] ?? null) || ! preg_match('/^[a-f0-9]{64}$/D', $lock[$path])
            || ! hash_equals($lock[$path], hash_file('sha256', $realPath))) {
            throw new RuntimeException('Missing or changed FAQ source: '.$path.'. Review its FAQ translations before refreshing the source lock.');
        }
    }

    /** Require each item's English references to track all available translated counterparts. */
    private function translatedSources(string $root, array $sources): void
    {
        foreach ($sources as $path) {
            if (! str_starts_with($path, 'docs/user/en/')) {
                continue;
            }
            $filename = substr($path, strlen('docs/user/en/'));
            foreach (self::LOCALES as $locale) {
                $translated = 'docs/user/'.$locale.'/'.$filename;
                if (is_file($root.'/'.$translated) && ! in_array($translated, $sources, true)) {
                    throw new RuntimeException('FAQ item must declare its existing translated source: '.$translated);
                }
            }
        }
    }

    /** Decode a required catalog object without database access or permissive JSON recovery. */
    private function readJson(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Missing FAQ catalog or source lock.');
        }
        $value = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($value)) {
            throw new RuntimeException('FAQ JSON must contain an object.');
        }

        return $value;
    }
}
