---
title: Deducciones, datos del cliente y pagos de facturas
category: invoicing
order: 5
updated: 2026-11-16
---

# Deducciones, datos del cliente y pagos de facturas

El detalle de la factura muestra las deducciones, la identidad del cliente y el método de pago registrado.

## Partidas de deducción

Para una deducción en una factura eslovaca o checa, introduzca una cantidad negativa y un precio unitario positivo en una partida manual independiente, por ejemplo `-1 x 15,00`. El total de la factura debe seguir siendo positivo. Utilice el tipo de IVA correspondiente; se restan tanto la partida como su IVA. La deducción no puede estar vinculada a un artículo de inventario ni a un almacén. Desvincule una partida de inventario existente antes de utilizarla como deducción.

Las deducciones con cantidad negativa y precio positivo admiten PDF, correo, exportación UBL/ISDOC y preparación para eFaktúra con las condiciones de envío existentes. Los precios unitarios negativos siguen sin ser compatibles con las exportaciones estructuradas. Las facturas híbridas alemanas y los proveedores externos de impuestos estadounidenses quedan fuera de este flujo.

Las notas de crédito creadas a partir de estas facturas conservan la deducción sin modificar la factura original. La combinación de estas deducciones con descuentos porcentuales separados sigue sin ser compatible con las exportaciones estructuradas.

Los porcentajes introducidos manualmente admiten hasta cuatro decimales, por ejemplo `8,875 %`. Los tipos guardados en las líneas, los resúmenes de impuestos, las etiquetas del PDF y las exportaciones estructuradas conservan esa precisión.

## Identificadores del cliente

Si el contacto contiene un número de registro, un número fiscal o un identificador de IVA, aparecen en la vista previa de la factura y en el PDF. Las facturas emitidas conservan la identidad guardada del cliente. Editar el contacto después no modifica una factura emitida.

WooCommerce importa los identificadores compatibles del pedido. Los campos vacíos del pedido conservan los identificadores ya guardados en el contacto. Otra extensión de pago puede necesitar un mapeo adicional de campos.

Un PDF enviado automáticamente antes de la importación en el navegador solo puede incluir los datos del cliente disponibles en el servidor desde el pedido. Los identificadores guardados únicamente en el contacto del navegador no pueden añadirse retroactivamente a un documento ya enviado.

## Método de pago registrado

Las opciones de pago ofrecidas al cliente son distintas del método registrado. Los pedidos pagados de WooCommerce conservan la clasificación de la pasarela suministrada; los pagos bancarios conciliados muestran su origen bancario y los pagos verificados de BTCPay muestran BTCPay. Los pagos manuales se indican como tales. Las facturas antiguas sin pruebas guardadas muestran un método sin especificar; su historial no se adivina ni se reescribe.

## Enlaces de pago con Bitcoin

Emitir una factura no crea una factura de BTCPay. Su PDF y correo incluyen un enlace de pago de Satflux cuando el pago con Bitcoin está habilitado. El código QR abre la página de pago en el navegador. El pago de BTCPay se crea o reutiliza cuando el cliente confirma el pago con Bitcoin / Lightning. El emisor también puede crear el pago expresamente desde el detalle de la factura.

Un pago bancario o manual registrado impide nuevos pagos mediante el enlace de Satflux cuando el estado llega al servidor. Los pagos parciales y los cambios en las condiciones de pago invalidan la oferta anterior. Antes de volver a cobrar, el emisor debe usar la acción explícita de renovación en el detalle de la factura para crear una nueva oferta de pago. En el modo local-first, los cambios realizados sin conexión llegan al servidor cuando el navegador se conecta de nuevo; tenga en cuenta este retraso al confirmar un pago bancario.

Los enlaces directos de BTCPay distribuidos anteriormente mantienen su comportamiento original. Un pago bancario no se convierte en un pago de Bitcoin ni cancela automáticamente un pago de BTCPay ya creado.
