<script type="text/php">
if (isset($pdf)) {
    $font = $fontMetrics->getFont('DejaVu Sans');
    if ($font) {
        $pageLabel = {!! json_encode(__('Page')) !!};
        $pageText = $pageLabel.' {PAGE_NUM}/{PAGE_COUNT}';
        $size = 8;
        $color = [0.42, 0.45, 0.5];
        $pageWidth = $pdf->get_width();
        $pageHeight = $pdf->get_height();
        $rightMargin = 32;
        $bottomMargin = 30;
        $y = $pageHeight - $bottomMargin;
        $sample = $pageLabel.' 99/99';
        $textWidth = $fontMetrics->get_text_width($sample, $font, $size);
        $x = $pageWidth - $rightMargin - $textWidth;
        $pdf->page_text($x, $y, $pageText, $font, $size, $color);
    }
}
</script>
