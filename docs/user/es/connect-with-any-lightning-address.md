---
title: Conectar con cualquier dirección Lightning
category: wallet-connection
order: 2
---

# Conectar con cualquier dirección Lightning

Abre **Conexión de cartera**, elige **Dirección Lightning** e introduce `tu@cartera.com`. Satflux comprueba la **dirección concreta**, incluidas Blink, Blitz, Flash y Coinos. Ni la marca ni un dominio compartido garantizan LUD-21.

## Conexión Lightning directa

Si la dirección admite **LUD-21**, se conecta mediante LNAddressConnect. Una dirección antigua de Coinos y otra migrada a Coinos v3 pueden tener capacidades distintas incluso en `coinos.io`.

## Sin LUD-21 - CashuMelt (beta)

Si la dirección devuelve una factura Lightning válida sin endpoint de verificación del pago, Satflux comprueba la disponibilidad de CashuMelt. Muestra el mint y solicita consentimiento explícito antes de guardar. Conserva el mint existente; de lo contrario ofrece el predeterminado configurado por el operador.

La liquidación depende de la confianza en el mint y de las comisiones. Verifica el pago Lightning al proveedor de la dirección, no su transferencia interna posterior, por ejemplo a un saldo Ark. Consulta [Liquidación Lightning con Cashu](/documentation/cashu-lightning-settlement).

## Si no se puede comprobar la dirección

Un tiempo de espera agotado, una respuesta inválida, un plugin ausente o un pago pendiente muestran una opción de reintento o el motivo de indisponibilidad. Satflux no cambia de cartera silenciosamente. Cambiar una cartera existente sigue requiriendo confirmación y comprobaciones de seguridad; las facturas existentes conservan sus ajustes de pago originales.

Otras opciones: [Elegir tu cartera](/documentation/choosing-your-wallet). Una dirección de recepción alojada en tu tienda es una función distinta: [Direcciones Lightning de la tienda](/documentation/store-lightning-addresses).
