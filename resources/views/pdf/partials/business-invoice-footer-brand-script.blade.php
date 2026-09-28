{{-- Brand line + "Page X/Y" are drawn by App\Pdf\DomPdfDriver after rendering (no inline PHP). --}}
<meta name="satflux-pdf-footer" content="{{ json_encode(['brand' => __('Created with SATFLUX.io'), 'page' => __('Page')], JSON_UNESCAPED_UNICODE) }}">
