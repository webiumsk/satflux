---
title: Conciliación de pagos bancarios
category: invoicing
order: 5
meta_description: Importe extractos bancarios y concilie automáticamente los pagos con sus facturas.
---

# Conciliación de pagos bancarios

La conciliación bancaria relaciona los pagos recibidos con los documentos emitidos para mostrar qué facturas están pagadas.

## Importar transacciones

Abra **Pagos** en su empresa e importe un extracto bancario. Satflux admite formatos habituales:

- Exportaciones **CSV** del banco
- Archivos **CAMT** (ISO 20022)
- Extractos de **Wise**
- Correos de notificación de algunos bancos

Las transacciones duplicadas se detectan y se omiten, por lo que puede volver a importar un extracto con fechas coincidentes.

## Notificaciones bancarias por correo

Use la dirección que aparece en **Pagos > Importar** para la empresa seleccionada. En la facturación local, abrir o actualizar Pagos importa los correos ya procesados en el puente del servidor de esa misma empresa a los datos cifrados del dispositivo. No recupera del banco el historial de notificaciones que falta.

## Conciliación automática

Satflux relaciona las transacciones recibidas con las facturas mediante el **símbolo variable**, el importe y otras referencias. Las facturas conciliadas se marcan como pagadas; los casos ambiguos quedan pendientes de confirmación o conciliación manual.

Un movimiento saliente recién importado por correo bancario puede marcar un gasto existente como pagado si hay exactamente un gasto no pagado de la misma empresa que coincida en importe positivo completo, moneda y símbolo variable. Si el gasto no tiene símbolo variable, se usan los dígitos de su número de documento. Sin símbolo variable bancario, la referencia debe ser igual al número de documento completo. La fecha contable del banco será la fecha de pago. Los casos ambiguos, pagos parciales y pagos superiores quedan sin conciliar. Esta automatización de gastos se aplica a correos bancarios; CSV y Wise mantienen su comportamiento actual. Las filas históricas duplicadas no se vuelven a conciliar al actualizar; use la acción de conciliación automática del lote para revisarlas explícitamente.

Los correos recién importados sin el sentido del movimiento (cargo o abono) registrado no pueden marcar automáticamente un gasto como pagado, tampoco al repetir la conciliación del lote. Los movimientos antiguos guardados mantienen el comportamiento anterior de conciliación explícita por lotes.

Si hay otra conciliación en curso, espere a que termine antes de volver a intentarlo. Si no se pudo confirmar el resultado, recargue la pestaña antes de realizar otro intento.

Si la conciliación tiene éxito pero no se puede actualizar la lista, recargue la pestaña para ver los resultados guardados. En el modo local, la conciliación por lotes sigue disponible para los movimientos guardados sin conciliar aunque una importación interrumpida siga mostrando cero filas importadas. Tras un resultado sin confirmar, recargue la pestaña antes de utilizar esa acción explícita.

## Gastos

Puede convertir los pagos salientes en **gastos** para completar la exportación contable.
