---
title: Deducciones, datos del cliente y pagos de facturas
category: invoicing
order: 5
updated: 2026-11-13
---

# Deducciones, datos del cliente y pagos de facturas

El detalle de la factura muestra las deducciones, la identidad del cliente y el método de pago registrado.

## Partidas de deducción

Para una deducción en una factura eslovaca o checa, introduzca una cantidad negativa y un precio unitario positivo en una partida manual independiente, por ejemplo `-1 x 15,00`. El total de la factura debe seguir siendo positivo. Utilice el tipo de IVA correspondiente; se restan tanto la partida como su IVA. La deducción no puede estar vinculada a un artículo de inventario ni a un almacén. Desvincule una partida de inventario existente antes de utilizarla como deducción.

Las deducciones con cantidad negativa y precio positivo admiten PDF, correo, exportación UBL/ISDOC y preparación para eFaktúra con las condiciones de envío existentes. Los precios unitarios negativos siguen sin ser compatibles con las exportaciones estructuradas. Las facturas híbridas alemanas y los proveedores externos de impuestos estadounidenses quedan fuera de este flujo.

Las notas de crédito creadas a partir de estas facturas conservan la deducción sin modificar la factura original. La combinación de estas deducciones con descuentos porcentuales separados sigue sin ser compatible con las exportaciones estructuradas.

## Identificadores del cliente

Si el contacto contiene un número de registro, un número fiscal o un identificador de IVA, aparecen en la vista previa de la factura y en el PDF. Las facturas emitidas conservan la identidad guardada del cliente. Editar el contacto después no modifica una factura emitida.

WooCommerce importa los identificadores compatibles del pedido. Los campos vacíos del pedido conservan los identificadores ya guardados en el contacto. Otra extensión de pago puede necesitar un mapeo adicional de campos.

Un PDF enviado automáticamente antes de la importación en el navegador solo puede incluir los datos del cliente disponibles en el servidor desde el pedido. Los identificadores guardados únicamente en el contacto del navegador no pueden añadirse retroactivamente a un documento ya enviado.

## Método de pago registrado

Las opciones de pago ofrecidas al cliente son distintas del método registrado. Los pedidos pagados de WooCommerce conservan la clasificación de la pasarela suministrada; los pagos bancarios conciliados muestran su origen bancario y los pagos verificados de BTCPay muestran BTCPay. Los pagos manuales se indican como tales. Las facturas antiguas sin pruebas guardadas muestran un método sin especificar; su historial no se adivina ni se reescribe.
