---
title: "Cambio de cartera bloqueado por la supervisión de facturas"
category: wallet-connection
order: 8
meta_description: "Por qué una tienda nueva puede tener una factura automática de verificación y cuándo volver a intentar el cambio de cartera."
---

Satflux puede crear automáticamente una pequeña factura Lightning al verificar
la cartera receptora, incluso antes de su primera factura para un cliente.
No está destinada al pago y se archiva después de leerla.

**Archivar no termina la supervisión ni cancela una solicitud de pago Lightning.**
No se puede sustituir la cartera mientras una factura siga supervisada o en proceso.

Las nuevas verificaciones solicitan un minuto de validez y otro de supervisión.
Las antiguas pueden conservar el período más largo de la tienda, normalmente
alrededor de un día. Prevalecen el plazo real de BTCPay y el estado del pago;
no se garantiza el desbloqueo en dos minutos para todas las tiendas.

Abra las facturas de la tienda afectada en BTCPay e incluya las archivadas.
Revise el estado y el fin de supervisión si se muestran. Espere a que termine
y vuelva a intentar el cambio. Los pagos en proceso, las actualizaciones de
cartera sin resolver o la recuperación Cashu pueden prolongar el bloqueo.
Contacte con soporte si la causa sigue sin estar clara. No borre registros ni
marque facturas como pagadas para evitar la protección.

Comparta con soporte solo cantidades de registros, estados y plazos. No comparta
solicitudes de pago, enlaces de pago, identificadores, credenciales ni datos de clientes.
