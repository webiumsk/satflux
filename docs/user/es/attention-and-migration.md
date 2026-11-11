---
title: Por resolver y migración
category: invoicing
order: 35
meta_description: Revise tareas pendientes e importe exportaciones de SuperFaktura con los originales proporcionados.
---

# Por resolver y migración

## Revise las tareas de la empresa

Abra **Por resolver** en la navegación de la empresa. Pagos, Envíos,
Sincronización y Documentos recibidos muestran el motivo y un enlace al área
existente para resolverlo. Actualizar comprueba las fuentes disponibles; no envía
ni importa facturas ni confirma pagos. Las fuentes no disponibles siguen visibles.

Una lista vacía comprobada solo cubre las fuentes accesibles con sus permisos.
Las conciliaciones locales y el historial de envíos se revisan en este dispositivo;
el diagnóstico de sincronización afecta al dispositivo y la cuenta. Algunas
comprobaciones de recepción requieren al propietario de la empresa.

## Importe una exportación de hoja de cálculo

En **Herramientas > Importar**, elija SuperFaktura o una hoja genérica compatible.
Exporte las facturas emitidas a una hoja y seleccione empresa de destino, archivo,
formato de fechas y columnas. La importación ISDOC de gastos sigue en el flujo
de gastos. Los adaptadores de otros proveedores todavía no están disponibles.

La vista previa valida todo el archivo admitido y muestra errores, duplicados,
totales, pagos reales y saldos por moneda. Resuelva números contradictorios,
valores inválidos y datos de pago ambiguos antes de confirmar. Una fecha de pago
por sí sola no demuestra el pago completo. No se inventan fechas ausentes ni se
sobrescriben registros financieros existentes.

Puede añadir hasta 50 originales PDF/XML/ISDOC. El límite por archivo es
10 MiB y el total del archivo de exportación y originales es 20 MiB. Su guardado
y lectura se verifican antes de crear facturas. Permanecen con sus datos locales
y la sincronización cifrada. Puede descargarlos desde la página de importación
incluso después de reiniciar el navegador.

El archivo conserva la hoja de origen y los originales seleccionados como un
lote.

Para SuperFaktúra, seleccione un ISDOC o un PDF con ISDOC incrustado. El
asistente lee las líneas en su dispositivo y contrasta número, proveedor,
cliente, fecha, moneda e importe con la exportación y la empresa seleccionada.
Despliegue las líneas en la vista previa antes de confirmar. Si los datos no
pueden leerse o no coinciden, revise el aviso y los originales afectados.
Puede confirmar la importación resumida de facturas sin líneas verificadas.
Use el PDF original guardado para consultar la presentación y los datos
fiscales históricos.

Las deducciones verificadas se conservan como partidas negativas independientes
si coinciden los importes de las partidas, el IVA y el total de la factura.

Revise el resultado antes de repetir una importación interrumpida. Los duplicados
exactos ya completados se omiten; los cambios requieren revisión. Guarde sus
claves de recuperación y copias: el almacenamiento local no es un nuevo archivo
permanente en el servidor.

Actualice Satflux antes de restaurar una copia que contenga originales. Las
versiones antiguas rechazan ese formato. No modifique el archivo de copia original.

Si el asistente indica que la actualización del almacenamiento local no está
lista, cierre todas las pestañas de Satflux en este navegador y vuelva a abrir
la aplicación. Cree una nueva vista previa con los mismos archivos. Se omiten
los duplicados exactos completados y pueden continuar los registros pendientes
verificados. Los errores de confirmación también muestran el paso afectado.

Un aviso de cuota del relay indica un límite de sincronización. El importador
puede continuar tras verificar la escritura y lectura locales. Revise el límite
de almacenamiento o servicio del relay afectado en los ajustes de sincronización.

La lista muestra las empresas locales verificadas durante la sincronización
del relay. Las estadísticas locales del perfil se cargan sin leer los originales
ni esperar al uso del relay. Actualizar también puede calcular el tamaño de la
base de datos local. Una recuperación pendiente completa primero la verificación
de la cuenta y los datos.


## Zaprite

Seleccione Zaprite en Herramientas > Importar. Utilice una clave API de solo
lectura de la organización de origen o un JSON completo de Zaprite descargado
desde Satflux. La clave solo se utiliza para consultar y no se guarda. Puede
descargar el JSON antes de importar. Revise partidas, importes, pagos y duplicados
y confirme la importación en la empresa seleccionada.

El adaptador admite determinadas monedas fiat con dos decimales y pagos
confirmados con datos exactos de origen. BTC, LBTC y otras precisiones deben
excluirse expresamente. La vista previa muestra sus cantidades y se archiva el
JSON completo, incluidas las facturas excluidas. Estas no se convierten en
registros contables. El JSON es una exportación de la API, no el PDF original
de la factura. Revise primero en el origen los borradores, ajustes no admitidos,
pagos ambiguos y números de factura en conflicto.
