---
title: Importar gastos de SuperFaktura
category: invoicing
order: 12
updated: 2026-11-18
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

Justo después de seleccionar un archivo, la aplicación solicita permiso para procesarlo temporalmente en el servidor y comprobar si contiene ISDOC incrustado o texto legible de factura PDF. Puede conservar el adjunto sin procesarlo. El adjunto muestra el estado y permite repetir la comprobación. Si se encuentran datos compatibles, confirme la extracción por separado. La comprobación y la extracción se realizan antes de **Guardar** en el formulario; no se puede guardar mientras haya una decisión pendiente. Cada extracción correcta se descuenta una vez de su límite o créditos existentes. No se realiza OCR: puede adjuntar los documentos que solo contienen imágenes e introducir sus datos manualmente.

Los PDF con texto generan un borrador parcial a partir de etiquetas de factura reconocidas en eslovaco, checo o inglés. Pueden tener hasta **25 páginas**, dentro del límite existente de **10 MiB** por adjunto. Los campos ausentes, contradictorios o incompatibles quedan sin rellenar y no borran el proveedor ni la cuenta guardados. Compare los valores con el documento original. En los formularios de creación o edición, guarde después de revisarlos. En el detalle del gasto, la extracción de texto abre una vista previa; solo **Guardar valores extraídos** modifica el gasto. **Conservar solo el adjunto** mantiene sus campos. La extracción ISDOC existente en el detalle sigue aplicando los datos tras su confirmación. Los datos de pago de PDF con texto siempre requieren revisión y nunca marcan el gasto como pagado ni habilitan automáticamente el QR de pago. Si el importe extraído no tiene moneda, seleccione la moneda que figura en la factura antes de guardar.

Para usar **QR de pago** después de extraer texto, revise los datos de pago en el formulario de creación o edición; desde el detalle del gasto, abra **Editar**. Compare el documento original y el historial de pagos, verifique el destinatario exacto, el IBAN del proveedor, el importe, la moneda y el símbolo variable, y confirme que todo el importe sigue pendiente. Pulse **Guardar**. **QR de pago** estará disponible en la lista de gastos solo si se cumplen las condiciones de **Códigos QR de pago** más abajo. Elegir un contacto o extraer campos no sustituye esta revisión. Los pagos registrados o un vínculo bancario impiden la confirmación; la extracción no marca el gasto como pagado ni habilita el QR automáticamente.

Si se interrumpe el guardado de un adjunto grande, vuelva a intentarlo mientras el archivo siga seleccionado. Tras recargar la página, elimine el adjunto incompleto y seleccione de nuevo el archivo original. Complete la carga o elimine el adjunto incompleto antes de crear una copia de seguridad completa.

## Contacto e IBAN del proveedor

Al crear un gasto, seleccione opcionalmente **Contacto del proveedor**. Su IBAN se copia en **IBAN del proveedor**. Si no tiene IBAN, se utiliza la cuenta extraída de forma segura del PDF/XML del mismo proveedor; si no hay ninguna, el campo queda vacío. También puede introducir nombre e IBAN manualmente. No se reutiliza la cuenta extraída de un proveedor distinto.

Los gastos guardados conservan nombre y cuenta aunque cambie el contacto. Las ediciones manuales y los campos borrados se conservan en extracciones posteriores; cámbielos expresamente en el editor. La nota interna no determina el destinatario del QR.

## Códigos QR de pago

Un gasto pendiente en EUR con importe positivo, nombre del proveedor de hasta 70 caracteres e IBAN válido del proveedor y símbolo variable de hasta 10 dígitos ofrece **QR de pago**. Elija **PAY by square** o **PayMe** y compruebe destinatario, importe exacto y referencia en la aplicación bancaria. Crear o escanear el QR no marca el gasto como pagado. Para importes exactos mayores que PayMe no admite, utilice PAY by square.

Sin IBAN no hay QR. Los gastos pagados o cancelados, conflictos bancarios, abonos negativos e importaciones que requieren revisar el pago no ofrecen QR. Elegir un contacto no sustituye la revisión del pago. En un gasto ISDOC/Peppol antiguo pendiente, abra **Editar**, compruebe el documento original y el historial de pagos y confirme el destinatario, IBAN, importe, moneda y símbolo variable exactos y que el importe completo sigue pendiente. Al guardar, **QR de pago** estará disponible en la lista de gastos. Si cambia los datos de pago antes de guardar, vuelva a confirmarlos. Una fecha de pago registrada, un importe pagado distinto de cero o ambiguo y los vínculos bancarios impiden esta confirmación. Los datos del proveedor en las notas no se copian a los campos de pago.

## Añadir una nota de crédito recibida

En **Gastos**, seleccione **Nueva nota de crédito recibida**, o elija **Nota de crédito recibida** como tipo de documento. Introduzca su propio número y símbolo variable, proveedor e **importe total negativo**, y guarde. Los abonos permanecen identificados en Gastos y reducen sus totales. No generan QR de pago saliente ni se vinculan a cargos bancarios de gastos.

El detalle también ofrece **Añadir nota de crédito recibida**. Abre un formulario con proveedor e importe negativo, dejando vacíos el nuevo número y la referencia. No crea un vínculo permanente con el gasto original. Los abonos emitidos a clientes permanecen en Documentos > Notas de crédito.
