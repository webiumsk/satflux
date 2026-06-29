<script type="text/php">
if (isset($pdf)) {
    $font = $fontMetrics->getFont('DejaVu Sans');
    if ($font) {
        $pageWidth = $pdf->get_width();
        $pageHeight = $pdf->get_height();
        $y = $pageHeight - 40;

        $brandText = {!! json_encode(__('Created with SATFLUX.io'), JSON_UNESCAPED_UNICODE) !!};
        $brandSize = 6;
        $brandColor = [0.42, 0.45, 0.5];
        $brandWidth = $fontMetrics->get_text_width($brandText, $font, $brandSize);
        $brandX = ($pageWidth - $brandWidth) / 2;
        $pdf->page_text($brandX, $y, $brandText, $font, $brandSize, $brandColor);

        $pageLabel = {!! json_encode(__('Page'), JSON_UNESCAPED_UNICODE) !!};
        $pageText = $pageLabel.' {PAGE_NUM}/{PAGE_COUNT}';
        $pageSize = 5.5;
        $pageColor = [0.61, 0.64, 0.69];
        $sample = $pageLabel.' 99/99';
        $pageTextWidth = $fontMetrics->get_text_width($sample, $font, $pageSize);
        $pageX = $pageWidth - 32 - $pageTextWidth;
        $pdf->page_text($pageX, $y, $pageText, $font, $pageSize, $pageColor);
    }
}
</script>
