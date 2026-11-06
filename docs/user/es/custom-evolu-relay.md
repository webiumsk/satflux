---
title: Añadir un relay Evolu propio
category: security
order: 7
meta_description: Sincroniza datos de facturación cifrados con tu propio relay junto al predeterminado.
updated: 2026-11-06
---

# Añadir un relay Evolu propio

En la facturación local-first puedes conectar tu propio relay Evolu junto al predeterminado. Tu frase de recuperación y tus claves de cifrado siguen siendo las mismas. Ambos relays reciben datos cifrados, incluidos los de las empresas compartidas a las que tienes acceso.

1. Abre **Configuración de la cuenta > Evolu sync**.
2. Introduce el origen WebSocket seguro de tu relay, por ejemplo `wss://relay.example.com` o `wss://relay.example.com:8443`.
3. Mantén activada la opción de usar también el relay predeterminado y guarda. La página se recarga para aplicar las conexiones.
4. Antes de cerrar cualquier pestaña de Satflux, asegúrate de haber guardado tu [frase de recuperación](/documentation/backup-and-recovery). En cada dispositivo, inicia sesión y carga los ajustes actualizados; después cierra **todas las pestañas de Satflux** y vuelve a abrir Satflux. Así se reinicia el worker de sincronización existente; recargar una sola pestaña no basta.

La dirección debe usar `wss://` y no contener usuario, contraseña, ruta, parámetros ni fragmento. El servidor debe implementar el protocolo Evolu compatible con el cliente Evolu 7.x de Satflux; un servidor WebSocket genérico no basta.

Los ajustes existentes de relay propio siguen usando solo ese relay hasta que actives la opción del predeterminado adicional. Desactivarla usa solo tu relay. Borrar la dirección personalizada restaura el predeterminado configurado. Si la instalación desactiva su relay predeterminado, añadir uno propio no activa un relay público alternativo.

## Disponibilidad y recuperación

Las conexiones funcionan de forma independiente: un relay no disponible no desactiva la otra conexión. Los datos se concilian al suscribirse o reconectarse. Recibir datos de un relay no demuestra que el otro ya los tenga. Un relay recién añadido puede necesitar tiempo para ponerse al día.

La prueba de conexión comprueba la accesibilidad, no la compatibilidad del protocolo Evolu ni una copia de seguridad completa. Las estadísticas de uso corresponden al relay personalizado principal cuando está configurado. No confirman que todos los relays tengan una copia completa.

**Desactivar la sincronización en este dispositivo** desactiva todas las conexiones de relay en ese dispositivo. Los demás conservan sus ajustes. Quitar un relay detiene las conexiones futuras; no borra los datos que ya tiene almacenados.

Guarda tu [frase de recuperación](/documentation/backup-and-recovery) y exporta los datos periódicamente. La recuperación requiere tanto la frase correcta como una copia disponible de los datos.
