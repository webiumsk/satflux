<main class="mx-auto max-w-4xl px-4 py-12 sm:px-6" id="documentation-content">
    @if($article !== null || $notFound)
        <nav aria-label="Documentation" class="mb-6 flex flex-wrap gap-4 text-sm text-indigo-300">
            <a href="/documentation" class="underline">All documentation</a>
            @if($article !== null)
                <a href="/documentation/{{ $article['slug'] }}.md" class="underline">Markdown</a>
            @endif
        </nav>
    @endif

    @if($article !== null)
        <article class="min-w-0 break-words text-gray-200
            [&_a]:text-indigo-300 [&_a]:underline
            [&_blockquote]:border-l-2 [&_blockquote]:border-gray-600 [&_blockquote]:pl-4
            [&_h1]:my-5 [&_h1]:text-4xl [&_h1]:font-bold
            [&_h2]:my-4 [&_h2]:text-2xl [&_h2]:font-bold
            [&_h3]:my-3 [&_h3]:text-xl [&_h3]:font-semibold
            [&_li]:my-1 [&_ol]:list-decimal [&_ol]:pl-6 [&_ul]:list-disc [&_ul]:pl-6
            [&_p]:my-4 [&_pre]:overflow-x-auto [&_pre]:bg-gray-800 [&_pre]:p-3
            [&_table]:block [&_table]:overflow-x-auto
            [&_td]:border [&_td]:border-gray-600 [&_td]:p-2 [&_th]:border [&_th]:border-gray-600 [&_th]:p-2">
            @unless(str_contains($article['content'], '<h1>'))
                <h1>{{ $article['title'] }}</h1>
            @endunless
            {{-- UserDocsRepository escapes raw HTML and disables unsafe Markdown links. --}}
            {!! $article['content'] !!}
        </article>
    @elseif($notFound)
        <h1 class="text-4xl font-bold">Article not found</h1>
    @else
        <h1 class="mb-8 text-4xl font-bold">Documentation</h1>
        @foreach(collect($articles)->groupBy(fn ($article) => $article['category']['name'] ?? 'Other guides') as $category => $guides)
            <section class="mb-8">
                <h2 class="mb-3 text-2xl font-semibold">{{ $category }}</h2>
                <ul class="space-y-2">
                    @foreach($guides as $guide)
                        <li>
                            <a href="/documentation/{{ $guide['slug'] }}" class="text-indigo-300 underline">{{ $guide['title'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif
</main>
