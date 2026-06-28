#!/usr/bin/env node
/**
 * One-off patch: set Spanish strings for es.json keys still identical to en.json.
 * Run: node scripts/apply-es-missing-translations.mjs
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const esPath = path.resolve(__dirname, '../resources/js/locales/es.json');

/** @type {Record<string, string>} */
const patch = {
    // create_store wizard
    'create_store.title': 'Crear tienda',
    'create_store.step_basic_info': 'Información básica',
    'create_store.step_wallet_type': 'Tipo de monedero',
    'create_store.step_confirm': 'Confirmar',
    'create_store.store_name': 'Nombre de la tienda',
    'create_store.store_name_placeholder': 'Mi tienda increíble',
    'create_store.default_currency': 'Moneda predeterminada',
    'create_store.currency_placeholder': 'Seleccione o escriba la moneda (p. ej., USD, BTC, EUR)',
    'create_store.timezone': 'Zona horaria',
    'create_store.preferred_price_source': 'Fuente de precios preferida',
    'create_store.price_source_description':
        'La fuente de precios recomendada se elige según la moneda predeterminada.',
    'create_store.choose_wallet_backend': 'Elija su backend de monedero Lightning:',
    'create_store.blink_description':
        'Use el monedero Blink para pagos Lightning rápidos y fiables.',
    'create_store.connection_string': 'Cadena de conexión',
    'create_store.connection_string_help':
        'Pegue su cadena de conexión Blink con la URL del servidor, la clave API y el ID del monedero.',
    'create_store.review_store_settings': 'Revisar ajustes de la tienda',
    'create_store.store_name_label': 'Nombre de la tienda',
    'create_store.default_currency_label': 'Moneda predeterminada',
    'create_store.timezone_label': 'Zona horaria',
    'create_store.price_source_label': 'Fuente de precios',
    'create_store.wallet_type_label': 'Tipo de monedero',
    'create_store.connection_string_format':
        'Formato: type=blink;server=...;api-key=...;wallet-id=...',
    'create_store.descriptor_help':
        'Pegue el descriptor watch-only de Aqua o Bull Bitcoin (sin claves privadas).',

    // settings
    'settings.title': 'Ajustes de la tienda',
    'settings.back_to_store': '← Volver a la tienda',
    'settings.loading_settings': 'Cargando ajustes...',
    'settings.store_information': 'Información de la tienda',
    'settings.store_name': 'Nombre de la tienda',
    'settings.default_currency': 'Moneda predeterminada',
    'settings.currency_placeholder': 'Seleccione o escriba la moneda (p. ej., USD, BTC, EUR)',
    'settings.timezone': 'Zona horaria',
    'settings.preferred_price_source': 'Fuente de precios preferida',
    'settings.price_source_description':
        'La fuente de precios recomendada se elige según la moneda predeterminada.',
    'settings.additional_settings': 'Ajustes adicionales',
    'settings.additional_settings_description':
        'Las opciones adicionales de configuración de la tienda se gestionan en la interfaz de SATFLUX. Todos los ajustes configurables están disponibles arriba o en otras secciones de SATFLUX.',
    'settings.save_changes': 'Guardar cambios',
    'settings.saving': 'Guardando...',
    'settings.settings_updated': 'Ajustes actualizados correctamente',
    'settings.failed_to_update': 'No se pudieron actualizar los ajustes. Inténtelo de nuevo.',

    // auth
    'auth.verifying_email': 'Verificando correo...',
    'auth.email_verified_success': 'Su correo se ha verificado correctamente. Redirigiendo...',
    'auth.go_to_dashboard': 'Ir al panel',
    'auth.verifying_email_wait': 'Espere mientras verificamos su dirección de correo...',
    'auth.invalid_verification_link': 'Formato de enlace de verificación no válido.',
    'auth.verification_failed_status': 'La verificación falló con el estado {status}',
    'auth.failed_to_verify_email': 'No se pudo verificar el correo.',
    'auth.verification_failed': 'Verificación fallida: {status}',
    'auth.failed_to_connect': 'No se pudo conectar al servidor. Inténtelo de nuevo.',
    'auth.failed_to_verify_email_link':
        'No se pudo verificar el correo. El enlace puede ser inválido o haber expirado.',

    // account
    'account.add_credit': 'Añadir crédito',
    'account.add_credit_btn': 'Añadir crédito',
    'account.add_credit_failed': 'No se pudo añadir crédito. Inténtelo de nuevo.',
    'account.add_credit_modal_title': 'Añadir crédito',
    'account.amount_placeholder': 'Introduzca el importe',
    'account.amount_sats': 'Importe (SATS)',
    'account.auto_renewal': 'Renovación automática',
    'account.billing_information': 'Datos de facturación',
    'account.checkout_failed': 'No se pudo crear el checkout. Inténtelo de nuevo.',
    'account.credit_balance': 'Saldo de crédito: {amount}',
    'account.credit_invoice_created':
        'Factura de crédito creada. Revise su correo para las instrucciones de pago.',
    'account.current_plan_includes': 'El plan actual incluye:',
    'account.enterprise_plan': 'Plan Enterprise',
    'account.feature_1_store': '1 tienda',
    'account.feature_api': 'Integraciones API',
    'account.feature_auto_reports': 'Informes mensuales automáticos',
    'account.feature_basic': 'Funciones básicas',
    'account.feature_csv': 'Exportaciones CSV',
    'account.feature_custom_integrations': 'Integraciones personalizadas',
    'account.feature_dedicated_support': 'Soporte dedicado',
    'account.feature_invoices': 'Gestión de facturas',
    'account.feature_pos': 'Aplicaciones de punto de venta',
    'account.feature_priority_support': 'Soporte prioritario',
    'account.feature_sla': 'Garantías SLA',
    'account.feature_unlimited_ln': 'Lightning Addresses ilimitadas',
    'account.feature_unlimited_stores': 'Tiendas ilimitadas',
    'account.guest_upgrade_email_placeholder': "su{'@'}correo.tld",
    'account.next_billing': 'Próxima facturación: {date}',
    'account.next_charge_on': 'Próximo cargo el {date}',
    'account.notification_email': 'Correo de notificaciones',
    'account.password_update_failed': 'No se pudo actualizar la contraseña',
    'account.payment_method': 'Método de pago',
    'account.per_month': 'al mes',
    'account.per_month_paid_yearly': 'al mes (pagado anualmente)',
    'account.plan_badge_active': 'Activo',
    'account.plan_badge_inactive': 'Inactivo',
    'account.plan_badge_standard': 'Estándar',
    'account.plan_desc_enterprise': 'Funciones ilimitadas con soporte dedicado',
    'account.plan_desc_free': 'Funciones básicas para empezar',
    'account.pro_plan': 'Plan Pro',
    'account.profile_update_failed': 'No se pudo actualizar el perfil',
    'account.subscription_plan': 'Plan de suscripción',
    'account.upgrade_to_enterprise': 'Pasar a Enterprise',
    'account.upgrade_to_pro': 'Pasar a Pro',
    'account.upgrade_to_unlock': 'Actualice para desbloquear más capacidades:',

    // stores (still English)
    'stores.descriptor': 'Descriptor',
    'stores.last_connection_change': 'Último cambio',
    'stores.by': 'por',
    'stores.by_user_id': 'Por ID de usuario',
    'stores.aqua_limits_warning':
        'Aqua tiene límites mín./máx. (100-25.000.000 sats). No es ideal para micropagos (zaps) y puede tener comisiones más altas.',
    'stores.blink_keys_warning':
        'Cree claves API solo con permisos «read» y «receive». Nunca use «write»: permitiría gastar desde su monedero.',
    'stores.blink_dashboard_link': 'Panel de Blink',
    'stores.cashu_col_mint_poll': 'Consulta al mint',
    'stores.crowdfund_view_editor': 'Editor',
    'stores.view_plan_options': 'Ver opciones del plan',
    'stores.back_to_stores': 'Volver a tiendas',
    'stores.pay_button_slider': 'Deslizador',
    'stores.blink_connection_description':
        'Conecte su monedero Blink con una clave API de lectura y recepción. Lo mejor para velocidad y fiabilidad.',
    'stores.aqua_bitcoin_core': 'Monedero Aqua',
    'stores.aqua_connection_description':
        'Conecte un monedero solo lectura con un descriptor de salida. Configuración sin custodia.',
    'stores.descriptor_tab': 'Descriptor',
    'stores.format_help': 'Ayuda de formato',
    'stores.descriptor_examples': 'Ejemplos',
    'stores.manual_config_required': 'Puede ser necesaria la configuración manual por el equipo de soporte.',
    'stores.current_connection': 'Conexión actual',
    'stores.type': 'Tipo',
    'stores.masked_secret': 'Secreto enmascarado',
    'stores.testing': 'Probando...',
    'stores.test_connection': 'Probar conexión',
    'stores.save_connection': 'Guardar conexión',
    'stores.needs_support': 'Requiere soporte',
    'stores.recent_invoices': 'Facturas recientes',
    'stores.view_all': 'Ver todo',
    'stores.date': 'Fecha',
    'stores.invoice_id': 'ID de factura',
    'stores.amount': 'Importe',
    'stores.just_now': 'Ahora mismo',
    'stores.minutes_ago': 'hace {minutes} min',
    'stores.hours_ago': 'hace {hours} h',
    'stores.days_ago': 'hace {days} d',
    'stores.configure_wallet_connection': 'Configure la conexión Lightning de su monedero para',
    'stores.sales': 'Ventas',
    'stores.manage': 'Gestionar',
    'stores.manage_store_config': 'Gestione la configuración y preferencias de su tienda.',
    'stores.settings_tab_coming_soon': 'Esta sección estará disponible pronto.',
    'stores.settings_tab_settings': 'Ajustes',
    'stores.settings_tab_users': 'Usuarios',
    'stores.settings_tab_roles': 'Roles',
    'stores.settings_tab_webhooks': 'Webhooks',
    'stores.settings_open_in_btcpay': 'Abrir en BTCPay Server',
    'stores.settings_tab_users_desc':
        'Añada y elimine usuarios de la tienda y asigne roles. Todo se gestiona en este panel.',
    'stores.settings_tab_roles_desc':
        'Los roles definen qué puede hacer cada usuario en la tienda. Asigne roles en la pestaña Usuarios.',
    'stores.settings_tab_webhooks_desc':
        'Los webhooks envían eventos HTTP de su tienda a otro servidor.',
    'stores.settings_users_managed_in_panel':
        'Los usuarios de la tienda y sus roles se gestionan aquí. Puede añadir o eliminar usuarios y asignar roles (Propietario, Gestor, Empleado, Invitado) cuando se cargue la lista.',
    'stores.settings_webhooks_not_in_panel':
        'La gestión de webhooks no está disponible en el panel por ahora.',
    'stores.settings_role_owner':
        'Propietario - Acceso completo: ajustes, usuarios, apps, facturas y puede eliminar la tienda.',
    'stores.settings_role_manager':
        'Gestor - Puede gestionar ajustes, apps y facturas; no puede gestionar usuarios ni eliminar la tienda.',
    'stores.settings_role_employee':
        'Empleado - Puede ver la tienda y crear facturas; acceso limitado a ajustes.',
    'stores.settings_role_guest': 'Invitado - Acceso de solo lectura a la tienda.',
    'stores.settings_checkout_section': 'Pago',
    'stores.loading_invoices': 'Cargando facturas...',
    'stores.view_manage_transactions': 'Vea y gestione las transacciones de su tienda.',
    'stores.exporting': 'Exportando...',
    'stores.export_to_csv': 'Exportar a CSV',
    'stores.export_to_xlsx': 'Exportar a XLSX',
    'stores.xlsx_export_available_in_pro_message':
        'La exportación XLSX está disponible en PRO y superiores. Actualice para exportar facturas como archivos Excel.',
    'stores.all_statuses': 'Todos los estados',
    'stores.new': 'Nueva',
    'stores.paid_partially': 'Pagada (parcialmente)',
    'stores.settled': 'Liquidada',
    'stores.invalid': 'Inválida',
    'stores.expired': 'Expirada',
    'stores.from_date': 'Desde fecha',
    'stores.to_date': 'Hasta fecha',
    'stores.clear_filters': 'Borrar filtros',
    'stores.no_invoices_found': 'No se encontraron facturas',
    'stores.adjust_filters_create_invoice':
        'Pruebe a ajustar los filtros o cree una factura nueva.',
    'stores.failed_to_load_invoices': 'No se pudieron cargar las facturas.',
    'stores.loading_exports': 'Cargando exportaciones...',
    'stores.download_data_exports': 'Descargue sus exportaciones de datos.',
    'stores.no_exports_yet': 'Aún no hay exportaciones',
    'stores.exports_will_appear_here':
        'Las exportaciones generadas desde la sección Facturas aparecerán aquí.',
    'stores.exports_available_in_pro_message':
        'El historial de exportaciones y las exportaciones mensuales automáticas están disponibles en Pro. Actualice para acceder al historial y recibir facturas liquidadas por correo el primer día de cada mes.',
    'stores.export_id': 'ID de exportación',
    'stores.format': 'Formato',
    'stores.downloading': 'Descargando...',
    'stores.download': 'Descargar',
    'stores.retrying': 'Reintentando...',
    'stores.processing': 'Procesando...',
    'stores.waiting': 'Esperando...',

    // header
    'header.view_support': 'Ver soporte',

    // tickets
    'tickets.col_email': 'Correo',

    // plans
    'plans.features.webhooks': 'Webhooks',

    // remaining user-facing English fragments
    'account.evolu_stats_owner_id': 'ID del propietario',
    'stores.email_rules_placeholders_hint':
        "Marcadores de posición: '{'Invoice.Id'}', '{'Invoice.OrderId'}', '{'Invoice.Status'}', '{'Invoice.CheckoutLink'}', '{'Invoice.Metadata.key'}', '{'Store.Name'}', …",
    'apps.crowdfund_header_subtitle': 'Crowdfunding - {store}',

    // batch 2: remaining localized labels (brands/acronyms unchanged)
    'common.mobile_nav_info': 'Información',
    'account.plan_free': 'Gratis',
    'account.on': 'Activado',
    'account.off': 'Desactivado',
    'account.enterprise_price_period': '/mes',
    'account.feature_1_ln_address': '1 dirección Lightning',
    'account.feature_3_ln_addresses': '3 direcciones Lightning',
    'stores.cashu_lightning_address_label': 'Dirección Lightning',
    'stores.cashu_unit_sat': 'Sats',
    'stores.crowdfund': 'Crowdfunding',
    'stores.no_crowdfund': 'Sin crowdfunding',
    'stores.payment_method_pay_button': 'Botón de pago',
    'apps.crowdfund': 'Crowdfunding',
    'apps.open_crowdfund': 'Abrir crowdfunding',
    'tickets.no': 'No',
    'header.feature_crowdfund': 'Crowdfunding',
    'landing.step5_title': 'Crowdfunding',
    'landing.feature_crowdfund_title': 'Crowdfunding',
    'landing.invoicing_section.compare.values.no': 'No',
    'invoicing.recurring_type_proforma': 'Factura proforma',
    'invoicing.jurisdiction_asia': 'Asia',
    'invoicing.registry_group_asia': 'Asia',
    'invoicing.subtotal': 'Subtotal',
    'invoicing.send_email_cc': 'Copia (CC)',
    'invoicing.send_email_bcc': 'Copia oculta (BCC)',
    'invoicing.email_smtp_host': 'Servidor',
    'invoicing.efaktura_sapi_client_id': 'ID de cliente SAPI',
    'invoicing.efaktura_sapi_client_secret': 'Secreto de cliente SAPI',
    'evolu.poc_badge': 'PoC local-first',
    'evolu.relay_label': 'Relay',
    'invoicing.relay_sync_owner_hint':
        'ID del propietario (debe coincidir en ambos dispositivos): {owner}',
    'invoicing.relay_sync_push_ok':
        'Enviado al relay ({companies} empresas, {events} eventos sync). ID del propietario: {owner}. En el otro dispositivo verifique el mismo ID del propietario y actualice (~1 min).',
    'invoicing.relay_sync_force_push_ok':
        'Force push completado ({rows} filas). ID del propietario: {owner}. En el otro dispositivo, Restaurar desde relay (~1-2 min).',
    'landing.step7_plugins_title': 'Complementos',
    'landing.pricing_enterprise_webhooks': 'Webhooks',
    'invoicing.expense_import_field_total': 'Total',
    'invoicing.col_total': 'Total',
    'invoicing.total_preview': 'Total',
    'invoicing.col_line_total': 'Total',
    'invoicing.country_at': 'Austria',
    'invoicing.country_pt': 'Portugal',
    'invoicing.country_hk': 'Hong Kong',
    'invoicing.country_gi': 'Gibraltar',
    'invoicing.bank_summary_balance': 'Saldo',
    'invoicing.issuer_email': 'Correo',
    'tickets.physical': 'Presencial',
    'tickets.virtual': 'En línea',
    'raffles.manual_ticket_badge': 'Entrada manual',
};

function setPath(obj, dotPath, value) {
    const parts = dotPath.split('.');
    let cur = obj;
    for (let i = 0; i < parts.length - 1; i++) {
        if (!(parts[i] in cur)) {
            throw new Error(`Missing path segment: ${parts.slice(0, i + 1).join('.')}`);
        }
        cur = cur[parts[i]];
    }
    cur[parts[parts.length - 1]] = value;
}

const es = JSON.parse(fs.readFileSync(esPath, 'utf8'));
const patchKeys = Object.keys(patch);
if (patchKeys.length !== new Set(patchKeys).size) {
    const dupes = patchKeys.filter((k, i) => patchKeys.indexOf(k) !== i);
    throw new Error(`Duplicate patch keys: ${[...new Set(dupes)].join(', ')}`);
}
let applied = 0;
for (const [key, value] of Object.entries(patch)) {
    setPath(es, key, value);
    applied++;
}
fs.writeFileSync(esPath, `${JSON.stringify(es, null, 2)}\n`);
console.log(`Applied ${applied} Spanish translations to es.json`);
