---
title: Facturación electrónica eslovaca - limitaciones actuales
category: invoicing
order: 6
meta_description: Estado actual y limitaciones de la conexión eFaktura eslovaca en Satflux.
updated: 2026-11-10
---

# Facturación electrónica eslovaca - limitaciones actuales

**Todavía no se ha verificado una integración completa de Satflux con un proveedor de eFaktura eslovaca. Actualmente no presentamos Satflux como una solución lista para la facturación electrónica obligatoria en Eslovaquia.** Se está desarrollando una integración de recepción que todavía requiere verificación con el proveedor. No hay una fecha de finalización prometida.

## Exportación y entrega

Satflux puede exportar facturas como **UBL/XML**. Descargar el archivo no entrega la factura al destinatario ni confirma el cumplimiento de las obligaciones legales. Un proveedor debe aceptar el documento y realizar la entrega requerida. La carga manual solo es una opción si el proveedor elegido la admite; ese proceso completo todavía no se ha verificado en Satflux.

## Cuenta del proveedor y costes

El proveedor puede exigir una cuenta propia y acceso a la API con un coste adicional. Un buzón web gratuito no incluye necesariamente una conexión desde Satflux. Antes de pagar o depender de un proveedor, confirme su precio, las condiciones de uso y la compatibilidad con Satflux. Elegir al proveedor en el portal de la Administración Financiera eslovaca no crea automáticamente credenciales operativas en Satflux.

## Configuración y documentos existentes

Se conservan la configuración, el historial de documentos y los controles de integración existentes. Si ya utiliza la conexión, abra la configuración de **facturación electrónica** de su empresa para revisar el proveedor guardado y las opciones de envío o recepción. Mantenga sus credenciales en privado.

**Probar conexión** comprueba la autenticación. Un resultado correcto o una lista de comprobación completada no demuestra que el proveedor haya aceptado o entregado una factura, que funcione la recepción ni que se haya completado la comunicación de datos requerida. Compruebe el estado y las pruebas con su proveedor.

Cuando está disponible, el panel del documento muestra el estado del envío al proveedor y su referencia del documento. El buzón de facturas recibidas permite revisar los documentos recibidos e importarlos como gastos. Estos controles describen funciones existentes; su presencia no confirma una integración completa verificada con el proveedor.

## Planificación de la facturación

Hasta disponer de un procedimiento verificado, utilice un servicio que pueda demostrar el envío y la recepción que necesita. Confirme sus obligaciones y plazos concretos con la Administración Financiera eslovaca o con su asesor contable.

## Recepción con ePostman

Cuando esté disponible el piloto de recepción, seleccione ePostman en los ajustes de facturación electrónica de la empresa eslovaca correcta. Las cuentas de producción y de prueba son independientes. Complete la activación con el proveedor y compruebe el registro de recepción; una prueba de conexión correcta solo verifica las credenciales. Este conector no habilita el envío mediante ePostman.

Los documentos recibidos aparecen en la bandeja de **Gastos**. Revise el documento o descargue su XML original. La importación crea un gasto y guarda el original en sus datos locales cifrados. Si el adjunto falla o es demasiado grande, el elemento sigue disponible para recuperarlo; la existencia del gasto por sí sola no completa la importación. Las notas de crédito deben identificarse y tratarse como abonos, sin registrarlas silenciosamente como gastos positivos normales.

Hasta completar la importación local, el original permanece cifrado en el servidor con claves del servidor; también se procesan allí las credenciales del proveedor y el resumen extraído. Los originales pendientes no se eliminan automáticamente por antigüedad. Tras verificar la transferencia local, se elimina el contenido temporal del servidor. Esto no es un archivo permanente del servidor de Satflux. Confirme con el proveedor el acceso al archivo, la conservación y la exportación, también tras cancelar la cuenta.

Mantenga separadas las conexiones de cada empresa. Activar la recepción de una empresa eslovaca no activa la de una LLC extranjera. Esta guía no demuestra recepción verificada en la red real ni preparación de los reportes.
