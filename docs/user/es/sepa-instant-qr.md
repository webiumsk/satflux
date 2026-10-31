---
title: SEPA Instant QR (pagos bancarios en euros)
category: payments
order: 5
meta_description: Acepta transferencias bancarias en euros mediante un código QR SEPA Instant junto con Bitcoin.
---

# SEPA Instant QR (pagos bancarios en euros)

**SEPA Instant QR** permite aceptar **transferencias bancarias en euros** al pagar: el cliente escanea un código QR con su aplicación bancaria y envía una transferencia SEPA Instant. Esta opción complementa tus pagos con Bitcoin y Lightning.

## Configuración

1. Abre tu tienda y entra en **SEPA Instant QR**.
2. Introduce los datos de la cuenta bancaria que recibirá las transferencias en euros y activa el método.
3. Opcionalmente, configura el envío de notificaciones bancarias directamente a la dirección de correo bancario de la tienda. Los mensajes compatibles de Tatra banka pueden confirmar automáticamente los pagos recibidos tras su verificación.

## Verificación del correo bancario

La confirmación automática requiere un origen bancario verificado y una cuenta receptora, una referencia de pago, un importe y una moneda coincidentes. Los mensajes con pruebas ausentes o ambiguas no marcan una factura como pagada. Cuando la verificación automática no esté disponible, comprueba la transferencia en tu aplicación bancaria y confírmala manualmente.

El operador del servidor debe configurar un verificador de correo bancario de confianza. La entrega por un proveedor de correo electrónico no verifica por sí sola el banco remitente. Los formatos de notificación no admitidos y las versiones antiguas del plugin requieren verificación manual. El tiempo de confirmación depende de la entrega y el procesamiento de la notificación bancaria; no se garantiza una confirmación inmediata. FIO utiliza un intervalo de consulta limitado por el banco.

Los mensajes clásicos de texto sin formato compatibles de Tatra banka pueden confirmar pagos automáticamente tras verificar la firma bancaria, la cuenta receptora, la referencia, el importe y la moneda. Otros formatos, los mensajes sin firma y los correos de SLSP requieren verificación manual. La compatibilidad se limita al formato clásico documentado; consulta al operador del servidor si el banco cambia sus notificaciones. La confirmación automática mediante Fio y NOP sigue disponible.

## Dónde está mi pago

Cada pago pendiente o que necesita revisión cuya referencia empieza por `QR-` tiene un botón **Dónde está mi pago**. Consulta el diagnóstico público del notificador de pagos instantáneos NOP de la Administración Financiera de Eslovaquia y muestra la secuencia: ID de transacción creado, notificación bancaria guardada, asociada a la caja registradora, publicada y recibida.

Qué puedes esperar:

- **NOP conoce este ID** aparece en tiendas que confirman mediante NOP con un certificado eKasa y en pagos comunicados por una cuenta bancaria con notificaciones activadas (Tatra banka, SLSP).
- **NOP no ha visto este ID** es normal en tiendas con confirmación manual, mediante Fio o con correos bancarios que requieren revisión manual: sus referencias se generan localmente, por lo que NOP no tiene nada que mostrar aunque el dinero ya haya llegado y la factura esté confirmada. No significa que el cliente no haya pagado - comprueba tu cuenta bancaria.
- La secuencia nunca indica qué cuenta recibió el dinero. Comprueba la transferencia en tu aplicación bancaria antes de marcar un pago como pagado - satflux no confirma pagos desde esta pantalla.

[kdejemojaplatba.sk](https://www.kdejemojaplatba.sk/) es un visor independiente del mismo servicio y muestra los mismos datos.

## Notas

- Disponible para todas las cuentas, incluidas las de invitados.
- Los pagos llegan a **tu cuenta bancaria** - es una vía de pago en euros que controlas, separada de Bitcoin.
- Complementa la [facturación](/documentation/getting-started-with-invoicing) y la [conciliación de pagos bancarios](/documentation/bank-payment-matching), que permiten asociar las transferencias recibidas con tus facturas.
