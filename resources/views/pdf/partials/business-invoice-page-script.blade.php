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
        // Align with the bottom footer row (brand row), not the contact row above.
        $y = $pageHeight - 30;
        $sample = $pageLabel.' 99/99';
        $textWidth = $fontMetrics->get_text_width($sample, $font, $size);
        $x = $pageWidth - 32 - $textWidth;
        $pdf->page_text($x, $y, $pageText, $font, $size, $color);
    }
}
</script>
