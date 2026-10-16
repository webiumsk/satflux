---
title: Recuperar el saldo restante de Cashu
category: payments
order: 4
meta_description: Consulta las reservas de comisiones devueltas en Cashu y recupéralas en tu propia cartera.
---

# Recuperar el saldo restante de Cashu

CashuMelt puede conservar reservas de comisiones no utilizadas como saldo Cashu.
Por ello puede quedar saldo aunque los pagos hayan finalizado. El envío automático
por Lightning empieza con 100 sat de saldo apto del mismo mint; un saldo menor puede
seguir bloqueando el cambio de cartera.

Los ajustes de conexión Cashu muestran la última comprobación de seguridad antes
de editar. Si el cambio está bloqueado, sigue el enlace a liquidaciones y
recuperación. Actualizar este estado conserva los ajustes que hayas editado;
una comprobación no disponible no significa que sea seguro cambiar la cartera.

Con CashuMelt **1.4.0.5 o posterior**, el resumen distingue pagos fallidos, otros
pagos sin finalizar y registros completados que conservan datos de recuperación.
El total puede superar el número de filas fallidas. Las filas afectadas muestran
por qué bloquean el cambio y qué etapa requiere atención. Con plugins anteriores,
este desglose aparece como desconocido.

Un error al consultar el estado en el mint, como HTTP 400, no confirma si se pagó.
El reintento puede recuperar datos guardados compatibles, pero el error no demuestra
que un pago esté sin pagar. Si el mint original ya no proporciona el estado y no
hay datos de recuperación utilizables, debe investigarse con su operador. La
recuperación del saldo restante descrita abajo no resuelve estos registros de pago.

Con CashuMelt **1.4.0.4 o posterior**, abre las **Liquidaciones Cashu** de la tienda
y el panel de recuperación. Los importes se expresan en satoshis y los registros
se cuentan por separado. Solo el propietario registrado con correo verificado
puede descargar los tokens o verificar su recuperación.

1. Selecciona la descarga de tokens y confirma el aviso. Introduce tu contraseña
   si se solicita y después el código de seis dígitos recibido por correo.
2. Mantén privado el archivo `cashumelt-change.json`: contiene tokens utilizables.
   Importa los tokens de su matriz JSON en tu propia cartera Cashu compatible.
   No envíes el archivo al soporte ni a otras personas.
3. Tras importarlos, selecciona la verificación y confírmala con un nuevo código
   de correo. El mint debe confirmar que los tokens exportados se han consumido.

La descarga por sí sola no elimina el bloqueo. Satflux actualiza después el saldo
y la comprobación de seguridad; otros registros pendientes pueden seguir impidiendo
el cambio. Si pierdes la descarga o desconoces el resultado, actualiza el panel e
inicia un nuevo intento autorizado. Los envíos inciertos y las unidades no admitidas
pueden necesitar soporte; sus registros se conservan.

Los plugins antiguos muestran que esta función no está disponible. Una comprobación
no disponible no significa saldo cero. La sección existente **Cashu recovery** de
BTCPay sigue disponible para las operaciones de recuperación admitidas.
