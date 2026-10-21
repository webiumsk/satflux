---
title: Entradas y orden de los tipos
category: payments
order: 20
updated: 2026-10-22
---

# Entradas y orden de los tipos

Crea y edita eventos en la aplicación Tickets existente. El título, la descripción con formato, el tipo presencial o virtual, la ubicación, las fechas, la imagen, la moneda y la capacidad determinan la oferta pública.

## Ordenar tipos de entradas

Despliega un evento y abre los tipos de entradas. Activa la edición del orden, arrastra un tipo o utiliza los botones arriba/abajo y guarda. Cancelar descarta el borrador. El orden incluye los tipos inactivos; la oferta pública los omite y conserva el orden relativo. Los nuevos tipos se añaden al final. Los precios y las entradas emitidas no cambian.

Se necesita el plugin compatible **Satflux Tickets 2.1.0**. Si otro administrador ha cambiado el evento mientras editabas, vuelve a cargar el orden actual antes de guardar.

## Venta pública

Comparte el enlace público existente del evento en tu servidor BTCPay. Los visitantes eligen entradas e introducen los datos de los asistentes en una página; después pagan en BTCPay. Desde Satflux Tickets 2.1.1, la página utiliza el idioma del navegador y permite elegir idioma (EN/SK/ES/DE/CS). Desde la versión 2.2.0, los eventos presenciales usan la zona del lugar y los virtuales la del visitante; consulta la sección de zona horaria. Las entradas para un mismo correo se envían juntas; los distintos destinatarios reciben únicamente sus propias entradas. Conserva las instrucciones de acceso a eventos virtuales en la configuración del evento y del correo.

Desde Satflux Tickets 2.1.2, elige el idioma en la cabecera para actualizar la página al instante. Se conservan las entradas seleccionadas y los datos de destinatarios sin completar.

## Zona horaria del evento

Desde Satflux Tickets 2.2.0, los eventos presenciales muestran la hora del lugar y su desfase UTC, independientemente de la ubicación del visitante. Los eventos virtuales usan la zona del navegador del visitante, con UTC claramente indicado como alternativa. Selecciona la zona IANA del lugar en el formulario existente, por ejemplo Europe/Bratislava, e introduce la hora local del evento. La migración histórica aprobada asigna Europe/Bratislava a los eventos presenciales existentes sin cambiar los instantes UTC guardados. Cambiar solo la zona no reprograma el evento. Una hora inexistente o ambigua durante el cambio de horario debe corregirse antes de guardar. Actualiza los plugins antiguos para usar estos campos; los errores conservan el borrador.

Para un nuevo evento presencial, Satflux preselecciona la zona guardada de la tienda si es compatible. Puedes elegir otra zona del lugar en el mismo formulario. La elección se guarda en el evento; cambiar después la zona de la tienda no altera los eventos existentes. En BTCPay, selecciona explícitamente la zona del lugar.
