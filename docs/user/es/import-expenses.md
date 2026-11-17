---
title: Importar gastos de SuperFaktura
category: invoicing
order: 12
updated: 2026-11-16
---

# Importar gastos de SuperFaktura

En su empresa, abra **Herramientas > Importar**, seleccione **Gastos** y después **Importar gastos**. También puede usar **Gastos > Importar**.

1. Seleccione el archivo XLSX de SuperFaktura o un archivo XLS/CSV.
2. Revise la asignación de columnas. La fecha de emisión y el total son obligatorios.
3. Inicie la importación y revise las filas importadas y omitidas, junto con sus motivos.
4. **Ver gastos** muestra los registros de todos los años.

La exportación actual de 33 columnas se reconoce automáticamente. Se conservan los números originales, fechas, importes y símbolos de pago. Los datos del proveedor, categoría, pagos y partidas se guardan en la nota interna. No se crean contactos de proveedores ni partidas de gastos independientes.

Un pago con fecha e importe suficiente en la moneda del gasto lo marca como pagado. Los pagos parciales y en otra moneda quedan pendientes de revisión, conservando sus datos originales. Los archivos antiguos que solo incluyen la fecha de pago mantienen el comportamiento anterior de pagado.

Los números internos existentes se omiten dentro de la empresa. Sin número interno se genera uno nuevo; volver a importar esas filas crea gastos nuevos.

Límites: **10 MB, 2.000 filas y 256 columnas** por archivo. Las filas no válidas o demasiado largas se omiten sin truncarlas. Las fórmulas se tratan como texto.

En la facturación local, el archivo se procesa en su navegador y se guarda con los datos cifrados existentes. Guarde su frase de recuperación. Los adjuntos PDF se importan por separado.


## Buscar y filtrar gastos

Acote la lista con los botones de estado y el periodo de emisión. Al abrir se muestra el año actual; puede elegir todas las fechas, un periodo predefinido o límites propios. Abra **Más** para filtrar el importe, el vencimiento y el texto. La búsqueda incluye el número interno, el número del documento del proveedor, el título, el símbolo variable y las notas.

Confirme los campos avanzados con **Filtrar**. En el móvil, use el panel de filtros. **Borrar filtros** (en el móvil **Borrar todo**) elimina la búsqueda y los demás criterios. Al cambiar los filtros aplicados se borra la selección actual. Los totales y **seleccionar todo** usan el resultado filtrado completo, incluidos los registros de otras páginas.

## Valores iniciales de un gasto nuevo

El símbolo variable sigue los dígitos del número del documento, incluidos los ceros iniciales. La fecha de entrega sigue la fecha de recepción. Al editar manualmente uno de estos valores, se detiene su vínculo. Se conservan los valores explícitos extraídos de un adjunto; los gastos existentes mantienen sus valores guardados. Una fecha de entrega borrada manualmente permanece vacía después de guardar y volver a abrir.

Si el número de documento contiene más de 32 dígitos, el símbolo variable automático queda vacío y se conserva el número de documento. Al acortar el número, vuelve a aplicarse el valor predeterminado mientras no se edite el símbolo manualmente.

Los cambios locales de gastos esperan a que termine la conciliación bancaria en curso antes de guardarse. Si el navegador no puede coordinar los cambios, recargue la pestaña o utilice un navegador actualizado. Si no se puede confirmar un resultado, recargue y compruebe el registro guardado antes de realizar otro intento. Las modificaciones deliberadas posteriores de un gasto pagado conservan su estado de pago existente.

Desvincule el pago bancario antes de cambiar el importe o la moneda del gasto vinculado. Las conciliaciones sin conexión se vuelven a confirmar tras la sincronización. Si un vínculo requiere revisión, se muestra un aviso en Pagos y en el gasto; elimine el vínculo pendiente antes de volver a conciliar. Se conservan las demás conciliaciones y los pagos registrados por separado. Utilice clientes actualizados en todos los dispositivos.

## Resumen de gastos

El resumen bajo **Gastos** incluye todos los documentos que coinciden con los filtros actuales, en todas las páginas. Muestra el número, el total y el importe de los gastos no pagados. Seleccionar filas no modifica el resumen. Cada moneda tiene sus propios totales.

El importe de los gastos no pagados suma los importes completos de los documentos no marcados como pagados. Los pagos parciales guardados en las notas de importación no se deducen automáticamente; revise esos documentos antes de usar esta cifra como saldo pendiente.

## Adjuntos PDF en un gasto individual

Abra un gasto y añada su PDF. En la facturación local, los adjuntos y los campos extraídos se guardan con sus datos locales cifrados. Cada adjunto local puede ocupar como máximo **10 MiB**. En el formulario de creación o edición, pulse **Guardar** para guardar los adjuntos pendientes. En la página de detalle del gasto se guardan inmediatamente al terminar u omitir el procesamiento ISDOC opcional.

Puede permitir el procesamiento temporal en el servidor para detectar datos ISDOC incrustados. Cancelar mantiene el adjunto en local. Si se encuentra ISDOC, confirme por separado la extracción o conserve solo el adjunto. La extracción usa su límite y créditos existentes; no es OCR. Si se agota el límite, puede adjuntar el archivo e introducir los datos manualmente.

Si se interrumpe el guardado de un adjunto grande, vuelva a intentarlo mientras el archivo siga seleccionado. Tras recargar la página, elimine el adjunto incompleto y seleccione de nuevo el archivo original. Complete la carga o elimine el adjunto incompleto antes de crear una copia de seguridad completa.

## Códigos QR de pago

En un gasto pendiente en EUR con nombre del proveedor, IBAN válido del proveedor y símbolo variable de hasta 10 dígitos, seleccione **QR de pago** en su fila. El importe debe ser mayor que cero y tener como máximo 12 dígitos antes del separador decimal; el nombre del proveedor puede tener como máximo 70 caracteres. Cambie entre **PAY by square** y **PayMe** y compruebe el destinatario, el importe y el símbolo variable antes de confirmar en su aplicación bancaria. El código se genera en su navegador. Abrir PayMe abre de forma deliberada su enlace de pago. Escanear el código no marca el gasto como pagado. Para importes que PayMe no puede representar, utilice PAY by square; el importe nunca se redondea.

No se muestra ningún código para gastos pagados o anulados, conciliaciones bancarias en disputa ni importaciones con pagos parciales o pendientes de aclarar. Los datos de pago proceden de metadatos explícitos del proveedor; nunca se usa la cuenta de la propia empresa. Las nuevas importaciones compatibles de facturas electrónicas e ISDOC conservan la cuenta del proveedor. En importaciones anteriores sin estos metadatos hay que completar primero los datos de pago.

Si la extracción no contiene una referencia de pago, el símbolo variable queda en blanco y la cuenta bancaria del proveedor no se importa. Verifique el documento original e introduzca el símbolo variable correcto. El QR de pago seguirá sin estar disponible hasta que el nombre y el IBAN del proveedor también estén completos y verificados.
