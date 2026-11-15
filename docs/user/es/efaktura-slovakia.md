---
title: Facturación electrónica eslovaca - conexión ePostman
category: invoicing
order: 6
meta_description: Conecte la cuenta ePostman de su empresa, reciba facturas electrónicas e importe sus originales como gastos en Satflux.
updated: 2026-11-15
---

# Facturación electrónica eslovaca - conexión ePostman

La conexión admite **recepción y envío manual**. La recepción e importación de una factura y una nota de crédito se han verificado en la red de pruebas de Peppol. También se ha confirmado el envío manual de ambos tipos de documento a través de ePostman a Recommand en esa red de pruebas. La activación en producción, las comunicaciones de datos exigidas y las condiciones del archivo requieren confirmación independiente. Satflux no se presenta como una solución completa para la facturación electrónica obligatoria eslovaca.

En el perfil de la empresa, las guías de configuración aparecen como tarjetas compactas. Abra una tarjeta para ver su lista de pasos y la opción de ocultarla o recibir un recordatorio más tarde. El estado de conexión no verificada de eFaktúra sigue visible con la tarjeta cerrada.

## Conectar su empresa

Configure cada empresa por separado. Activar la recepción de una empresa eslovaca no activa la de una LLC extranjera.

1. Abra la configuración de **facturación electrónica** de la empresa correcta y active la facturación electrónica. Elija **ePostman** y el entorno adecuado. Las cuentas de producción y de prueba tienen credenciales independientes; al cambiar de entorno, necesita las credenciales de ese entorno. Satflux completa la dirección del servidor de ePostman.
2. Complete la activación de su empresa en ePostman. Para producción, siga la [guía de activación del proveedor](https://postman.slovakodata.com/help/zaciname/onboarding/) y confirme el registro de recepción. Elegir un proveedor en Satflux no registra la empresa con el proveedor ni con la Administración Financiera eslovaca. Para las pruebas, utilice exclusivamente el portal y las credenciales de prueba.
3. En el portal ePostman, abra **Nastavenia > SAPI klienti** y cree un cliente dedicado a Satflux. Copie su **Client ID** y **Client Secret** en Satflux. Son credenciales de cliente SAPI, no una clave de la API REST. El proveedor muestra el secreto solo al crearlo; manténgalo en privado. Consulte la [guía de configuración del proveedor](https://postman.slovakodata.com/help/portal/nastavenia/).
4. Compruebe la identidad de la empresa que muestra Satflux. El identificador de recepción se obtiene de los datos de la empresa. Si el asistente indica que falta el DIČ, complete primero el identificador fiscal eslovaco correcto en los datos de la empresa. Utilice credenciales que pertenezcan a esa empresa.
5. Seleccione **Probar conexión**. La prueba solo verifica las credenciales. No confirma el registro de recepción, la entrega de un documento ni el cumplimiento legal. Corrija cualquier error antes de continuar.
6. Mantenga activada la recepción y **guarde** la configuración. Una prueba correcta no guarda por sí sola toda la configuración de recepción. Resuelva cualquier error de guardado o sincronización. Tras guardar correctamente, Satflux puede descargar documentos entrantes en segundo plano, incluso con el navegador cerrado. Si el formulario pide otra prueba después de guardar una conexión modificada, repítala para verificar las credenciales guardadas.

Si ya hay un secreto guardado para la misma conexión, deje su campo vacío para conservarlo. Al cambiar de proveedor o de entorno, introduzca las nuevas credenciales y repita la prueba.

## Conexiones separadas para recepción y envío

Una empresa que solo recibe ve una única configuración de **Proveedor de recepción** con su conexión existente. Seleccione el proveedor, introduzca o conserve sus credenciales, pruebe la conexión y guarde. En este modo no aparece la opción de conexión de envío. Abrir los ajustes no mueve credenciales ni cambia el proveedor.

Las empresas que envían y reciben tienen secciones separadas de **Proveedor de envío** y **Proveedor de recepción**. De forma predeterminada está marcada **Usar la conexión de envío para recibir**: la recepción utiliza el proveedor y las credenciales de la sección de envío. Desmárquela para configurar un proveedor de recepción compatible con sus propias credenciales. Ambos conjuntos de datos se conservan al cambiar esta opción; guarde para aplicar el cambio. Una recepción separada incompleta no recurre a la conexión de envío.

Si una empresa que solo recibe tiene una configuración separada vacía y existe una conexión anterior guardada, **Usar la conexión anterior** la selecciona en el formulario. Guarde para aplicar la selección. Los datos separados existentes y los cambios en curso no se sustituyen automáticamente.

La comprobación del buzón utiliza la conexión de recepción; el envío manual y la clave API Enterprise utilizan la conexión de envío. Cambiar los datos de recepción no modifica un intento de envío ni su historial. Confirme un envío incierto con su proveedor original; Satflux no lo envía automáticamente a través de otro proveedor.

Seleccionar un proveedor aquí no registra ni transfiere el servicio de recepción. Solo puede haber un proveedor de recepción por DIČ; acuerde cualquier traslado por separado. Una opción predefinida no demuestra compatibilidad probada con todos los proveedores. Las conexiones separadas requieren un servidor Satflux compatible.

## Encontrar e importar documentos recibidos

En la misma empresa, abra **Gastos > Facturas electrónicas recibidas**. Revise el documento o descargue su XML original y, después, impórtelo como gasto. Las notas de crédito se importan como correcciones con el signo correspondiente. Una descarga correcta sin documentos significa que no se han descargado documentos nuevos; no confirma la entrega de una factura de prueba.

Usa **Comprobar buzón** directamente en **Gastos > Facturas electrónicas recibidas** para consultar los documentos entrantes en el proveedor y después actualizar el buzón y el estado de recepción. **Actualizar** solo carga documentos y estados ya almacenados en Satflux. **Descargar entrantes ahora** sigue disponible en los ajustes de facturación electrónica de la empresa. Los documentos ya importados permanecen en Gastos; el buzón solo muestra los que esperan la importación local. Un buzón vacío es normal después de importar.

Tras una prueba local de conexión correcta, el formulario indica que la verificación aún debe guardarse. Usa **Guardar mis datos** para conservarla. Guardar la misma conexión y las mismas credenciales conserva la última comprobación del buzón; cambiar o eliminar las credenciales invalida esa evidencia. Si no hay una comprobación registrada o el estado es antiguo, usa **Comprobar buzón**. Una comprobación correcta sin documentos no confirma la entrega ni la preparación para producción.

El XML original compatible de hasta 10 MiB puede importarse a tus datos locales de Evolu. La sincronización utiliza cifrado de extremo a extremo. El original completo se verifica antes de confirmar la recepción local. Si se interrumpe, repite la importación para reparar el mismo gasto y adjunto sin duplicarlo. La existencia de un gasto por sí sola no completa la importación. El límite superior se aplica a las facturas electrónicas recibidas; los adjuntos locales añadidos manualmente mantienen su límite de 384 KiB.

Usa una aplicación actualizada en cada dispositivo que abra o respalde originales grandes. Restaurar su nuevo formato de copia también requiere una aplicación actualizada. Los clientes antiguos no garantizan el acceso ni copias completas de estos originales.

La exportación para el contable mantiene su límite independiente de 512 KiB por adjunto. Cuando se incluyen adjuntos, los originales mayores se indican como omitidos; descarga los XML necesarios por separado.

Si el UBL original contiene adjuntos PDF incrustados, el detalle del documento ofrece **Ver** y **Descargar** para cada PDF. Estas acciones también están disponibles al seleccionar el adjunto XML original de un gasto ya importado. La vista muestra las páginas; la descarga conserva los bytes del PDF incrustado. El XML original no cambia. Satflux no consulta enlaces externos de adjuntos. Se admiten hasta diez PDF de hasta 1 MiB cada uno en un XML de hasta 2 MiB. Los XML originales mayores pueden guardarse y descargarse aunque superen este límite de vista previa. Los PDF no compatibles, dañados o demasiado grandes muestran un aviso. Si falla la vista previa, aún puedes descargar un PDF extraído correctamente.

## Conservación de originales y costes del proveedor

Hasta completar la importación local verificada, el original permanece cifrado en el servidor con claves del servidor. También se procesan allí las credenciales del proveedor y el resumen extraído del documento. Los originales pendientes no se eliminan automáticamente por antigüedad. Tras verificar la transferencia local, se elimina el contenido temporal del servidor. Esto no es un archivo permanente del servidor de Satflux. Confirme con el proveedor el acceso al archivo, la conservación y la exportación, también tras cancelar la cuenta.

La cuenta del proveedor puede tener tarifas de API o condiciones de uso independientes. Un buzón web gratuito no incluye necesariamente acceso desde Satflux. Antes de utilizar la conexión en producción, confirme con ePostman el plan aplicable, el acceso SAPI y las condiciones del archivo.

## Envío manual y obligaciones legales

Para este envío nacional, ambas partes deben estar en Eslovaquia y usar `0245:<DIČ>` con su propio DIČ de diez dígitos.

Esta primera versión de envío rechaza documentos con descuentos por línea o factura, pérdida de precisión en los precios o importes distintos del documento emitido. Es necesario ampliar el formato de exportación antes de enviar esos documentos. Los pagos parciales se incluyen en el saldo del XML.

Para una empresa eslovaca elegible con registro completo de IVA, abra una factura B2B nacional emitida o una nota de crédito y seleccione **Enviar factura electrónica**. Guarde primero la conexión correcta de la empresa. El emisor y las credenciales deben pertenecer a la misma empresa. El envío automático mediante ePostman permanece desactivado.

Un envío correcto significa **aceptado para su procesamiento**. La entrega y la comunicación tributaria se muestran por separado cuando se configura una clave de la API Enterprise. El enlace al portal de ePostman sigue disponible en el entorno de producción o pruebas correspondiente.

Si el resultado es incierto, repita el intento solo cuando Satflux lo permita. El reintento utiliza la solicitud y el identificador originales dentro del plazo del proveedor. Tras vencer el plazo o cambiar el documento o la conexión, confirme primero el resultado con el proveedor. No cree una nueva factura únicamente para repetir un envío incierto.

## Estado de entrega automático

En la configuración de facturación electrónica de la misma empresa, introduzca una **clave API de ePostman** de **Configuración > Claves API** del portal de producción o pruebas correspondiente y guarde. Es independiente del SAPI Client ID y Client Secret. Satflux guarda esta clave cifrada en su servidor y no la incluye en los datos compartidos de la empresa. Manténgala privada. Eliminar la clave API desactiva las comprobaciones automáticas de estado; la recepción y el envío SAPI usan sus credenciales existentes.

Satflux comprueba periódicamente los documentos enviados, incluso con el navegador cerrado. El panel del documento muestra por separado la entrega, la confirmación **C3 MLS** del destinatario y el estado técnico **C5** comunicado por el proveedor. En documentos compatibles puede usar **Actualizar estado**, respetando una breve espera entre comprobaciones. Un panel abierto y visible también recarga los resultados guardados.

**UNCONFIRMED / TIMEOUT** significa que el punto de acceso receptor recibió el mensaje, pero la confirmación MLS final no llegó a tiempo. Por sí solo, no significa que el envío fallara. Un C5 positivo es un estado técnico comunicado por el proveedor. No confirma la aprobación fiscal ni la aprobación comercial del destinatario. Si una comprobación falla, el último resultado válido sigue visible junto con el error. Sustituya las claves API caducadas o revocadas en la configuración de la empresa.

Las comprobaciones automáticas pueden no estar disponibles para envíos anteriores. Consulte esos documentos en el portal del proveedor. No vuelva a enviar una factura existente solo para obtener su estado.

Confirme las obligaciones y los plazos concretos con la Administración Financiera eslovaca o con su asesor contable. Una conexión operativa por sí sola no confirma el cumplimiento de todas las obligaciones de envío y comunicación de datos.
