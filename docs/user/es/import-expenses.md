---
title: Importar gastos de SuperFaktura
category: invoicing
order: 12
updated: 2026-11-03
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

## Resumen de gastos

El resumen bajo **Gastos** incluye todos los documentos que coinciden con los filtros actuales, en todas las páginas. Muestra el número, el total y el importe de los gastos no pagados. Seleccionar filas no modifica el resumen. Cada moneda tiene sus propios totales.

El importe de los gastos no pagados suma los importes completos de los documentos no marcados como pagados. Los pagos parciales guardados en las notas de importación no se deducen automáticamente; revise esos documentos antes de usar esta cifra como saldo pendiente.

## Adjuntos PDF en un gasto individual

Abra un gasto y añada su PDF. En la facturación local, los adjuntos y los campos extraídos se guardan con sus datos locales cifrados. Cada adjunto local puede ocupar como máximo **384 KiB**. En el formulario de creación o edición, pulse **Guardar** para guardar los adjuntos pendientes. En la página de detalle del gasto se guardan inmediatamente al terminar u omitir el procesamiento ISDOC opcional.

Puede permitir el procesamiento temporal en el servidor para detectar datos ISDOC incrustados. Cancelar mantiene el adjunto en local. Si se encuentra ISDOC, confirme por separado la extracción o conserve solo el adjunto. La extracción usa su límite y créditos existentes; no es OCR. Si se agota el límite, puede adjuntar el archivo e introducir los datos manualmente.
