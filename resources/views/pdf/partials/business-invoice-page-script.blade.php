<script type="text/php">
if (isset($pdf)) {
    $font = $fontMetrics->getFont('DejaVu Sans');
    if ($font) {
        $pageLabel = {!! json_encode(__('Page')) !!};
        $pageText = $pageLabel.' {PAGE_NUM}/{PAGE_COUNT}';
        $size = 8;
        $color = [0.42, 0.45, 0.5];
        $pageWidth = 595.28;
        $rightMargin = 32;
        $y = 812;
        $sample = $pageLabel.' 99/99';
        $textWidth = $fontMetrics->get_text_width($sample, $font, $size);
        $x = $pageWidth - $rightMargin - $textWidth;
        $pdf->page_text($x, $y, $pageText, $font, $size, $color);
    }
}
</script>
