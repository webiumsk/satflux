---
title: Facturación electrónica eslovaca - recepción con ePostman
category: invoicing
order: 6
meta_description: Conecte la cuenta ePostman de su empresa, reciba facturas electrónicas e importe sus originales como gastos en Satflux.
updated: 2026-11-12
---

# Facturación electrónica eslovaca - recepción con ePostman

La conexión con ePostman admite la **recepción**. La recepción e importación de una factura y una nota de crédito se han verificado en la red de pruebas de Peppol. La activación en producción, las comunicaciones de datos exigidas y las condiciones del archivo del proveedor requieren confirmación independiente. Satflux todavía no se presenta como una solución completa para la facturación electrónica obligatoria eslovaca.

## Conectar su empresa

Configure cada empresa por separado. Activar la recepción de una empresa eslovaca no activa la de una LLC extranjera.

1. Abra la configuración de **facturación electrónica** de la empresa correcta y active la facturación electrónica. Elija **ePostman** y el entorno adecuado. Las cuentas de producción y de prueba tienen credenciales independientes; al cambiar de entorno, necesita las credenciales de ese entorno. Satflux completa la dirección del servidor de ePostman.
2. Complete la activación de su empresa en ePostman. Para producción, siga la [guía de activación del proveedor](https://postman.slovakodata.com/help/zaciname/onboarding/) y confirme el registro de recepción. Elegir un proveedor en Satflux no registra la empresa con el proveedor ni con la Administración Financiera eslovaca. Para las pruebas, utilice exclusivamente el portal y las credenciales de prueba.
3. En el portal ePostman, abra **Nastavenia > SAPI klienti** y cree un cliente dedicado a Satflux. Copie su **Client ID** y **Client Secret** en Satflux. Son credenciales de cliente SAPI, no una clave de la API REST. El proveedor muestra el secreto solo al crearlo; manténgalo en privado. Consulte la [guía de configuración del proveedor](https://postman.slovakodata.com/help/portal/nastavenia/).
4. Compruebe la identidad de la empresa que muestra Satflux. El identificador de recepción se obtiene de los datos de la empresa. Si el asistente indica que falta el DIČ, complete primero el identificador fiscal eslovaco correcto en los datos de la empresa. Utilice credenciales que pertenezcan a esa empresa.
5. Seleccione **Probar conexión**. La prueba solo verifica las credenciales. No confirma el registro de recepción, la entrega de un documento ni el cumplimiento legal. Corrija cualquier error antes de continuar.
6. Mantenga activada la recepción y **guarde** la configuración. Una prueba correcta no guarda por sí sola toda la configuración de recepción. Resuelva cualquier error de guardado o sincronización. Tras guardar correctamente, Satflux puede descargar documentos entrantes en segundo plano, incluso con el navegador cerrado. Si el formulario pide otra prueba después de guardar una conexión modificada, repítala para verificar las credenciales guardadas.

Si ya hay un secreto guardado para la misma conexión, deje su campo vacío para conservarlo. Al cambiar de proveedor o de entorno, introduzca las nuevas credenciales y repita la prueba.

## Encontrar e importar documentos recibidos

En la misma empresa, abra **Gastos > Facturas electrónicas recibidas**. Revise el documento o descargue su XML original y, después, impórtelo como gasto. Las notas de crédito se importan como correcciones con el signo correspondiente. Una descarga correcta sin documentos significa que no se han descargado documentos nuevos; no confirma la entrega de una factura de prueba.

Para iniciar una descarga manual, utilice **Descargar entrantes ahora** en la configuración de facturación electrónica de la empresa. Después, actualice la bandeja de entrada en Gastos. Actualizar la bandeja muestra los documentos que Satflux ya ha descargado.

La importación guarda el original en sus datos locales cifrados. Si falla o el original es demasiado grande para un adjunto local, el elemento sigue disponible para recuperarlo y descargar el original. La existencia de un gasto por sí sola no completa la importación.

## Conservación de originales y costes del proveedor

Hasta completar la importación local verificada, el original permanece cifrado en el servidor con claves del servidor. También se procesan allí las credenciales del proveedor y el resumen extraído del documento. Los originales pendientes no se eliminan automáticamente por antigüedad. Tras verificar la transferencia local, se elimina el contenido temporal del servidor. Esto no es un archivo permanente del servidor de Satflux. Confirme con el proveedor el acceso al archivo, la conservación y la exportación, también tras cancelar la cuenta.

La cuenta del proveedor puede tener tarifas de API o condiciones de uso independientes. Un buzón web gratuito no incluye necesariamente acceso desde Satflux. Antes de utilizar la conexión en producción, confirme con ePostman el plan aplicable, el acceso SAPI y las condiciones del archivo.

## Envío y obligaciones legales

Este conector de ePostman no habilita el envío ni el envío automático. Satflux puede exportar **UBL/XML**, pero descargar un archivo no lo entrega al destinatario. La carga manual solo es una opción si el proveedor elegido admite y verifica ese proceso completo.

Confirme las obligaciones y los plazos concretos de su empresa con la Administración Financiera eslovaca o con su asesor contable. Una recepción operativa por sí sola no confirma el cumplimiento de todas las obligaciones de envío y comunicación de datos.
