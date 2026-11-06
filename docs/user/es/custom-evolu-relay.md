---
title: Gestionar relays Evolu
category: security
order: 7
meta_description: Gestiona la sincronización cifrada de facturas mediante varios relays.
updated: 2026-11-07
---

# Gestionar relays Evolu

Satflux conserva los datos de facturación en tu dispositivo y sincroniza los cambios cifrados mediante relays. La lista predeterminada contiene `wss://evolu.satflux.io` y `wss://free.evoluhq.com`. Puedes añadir otros relays compatibles, hasta ocho servidores configurados.

1. Abre **Ajustes de cuenta > Evolu sync**. Cada fila muestra la dirección y el estado actual de conexión.
2. Elige **Añadir relay**, introduce una dirección segura como `wss://relay.example.com:8443` y confirma **Añadir relay**. Los relays existentes permanecen en la lista.
3. Abre una fila para ver la conexión y activar o desactivar **Usar este relay**. Los relays predeterminados permanecen en la lista y se pueden desactivar. Un relay propio se puede eliminar tras confirmarlo.
4. Guarda tu [frase de recuperación](/documentation/backup-and-recovery) antes de cerrar pestañas. En cada dispositivo, inicia sesión, carga los ajustes actualizados, cierra **todas las pestañas de Satflux** y vuelve a abrir Satflux. Esto reinicia el worker de sincronización existente; recargar una sola pestaña no basta.

Los cambios de la lista se aplican a tu cuenta. **Desactivar la sincronización en este dispositivo** pausa todos los relays solo en este dispositivo. Desactivar o eliminar un relay conserva los datos locales y no borra los ya guardados en el servidor.

## Estados de conexión

**Conectado** significa que una conexión WebSocket real de sincronización está abierta. **Conectando**, **Desconectado** y **Error de conexión** describen esa conexión. **Desconocido** indica que no hay información actual disponible; después de una actualización puede ser necesario reiniciar todas las pestañas. **Sincronización pausada** indica que el propietario local actual aún no está autorizado para sincronizar; restaura la facturación con la frase correcta. Los relays desactivados y la pausa del dispositivo se muestran por separado.

Una conexión abierta no confirma compatibilidad del protocolo, escrituras correctas ni una copia completa. El cliente instalado necesita un relay compatible con Evolu 7.x. Las conexiones funcionan de forma independiente y el historial faltante se reconcilia al suscribirse o reconectarse. Los relays no se copian directamente entre sí; los sincroniza un cliente. Deja tiempo para que un relay nuevo reciba los datos.

Las direcciones deben usar `wss://`, sin credenciales, ruta, parámetros ni fragmento. Las direcciones duplicadas se rechazan. Los ajustes existentes de solo relay propio se conservan; usa **Añadir relays predeterminados** para incluirlos expresamente. Una instalación con el relay predeterminado desactivado no activa relays públicos automáticamente. Las direcciones HTTPS antiguas usan la conexión WSS equivalente. Con un relay predeterminado WS/HTTP inseguro, el administrador debe configurar una dirección segura antes de editar la lista.

## Disponibilidad y recuperación

`free.evoluhq.com` es un relay de pruebas y sus datos pueden borrarse. Consulta la [documentación de Evolu](https://www.evolu.dev/docs/relay). Conserva una [exportación de datos](/documentation/backup-and-recovery) independiente y tu frase de recuperación. Restaurar requiere la frase correcta y una copia disponible de los datos; la frase por sí sola no contiene facturas.

El tamaño de SQLite local describe este dispositivo y las estadísticas del relay solo describen el primer relay activado. Ninguno confirma una copia completa en todos los relays.
