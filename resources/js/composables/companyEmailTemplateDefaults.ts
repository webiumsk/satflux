import type { EmailTemplate, EmailTemplateKey } from './useCompanyEmailSettings';

type LocaleCode = 'sk' | 'en' | 'es';

/** Default document email/SMS templates per UI locale (mirrors CompanyEmailSettings::defaultTemplates on server). */
export const EMAIL_TEMPLATE_DEFAULTS: Record<LocaleCode, Record<EmailTemplateKey, EmailTemplate>> = {
  sk: {
    invoice: {
      subject: '#MY_COMPANY# - Faktúra #NUMBER#',
      body: 'Dobrý deň,\n\nv prílohe tohto mailu vám posielame faktúru číslo #NUMBER# na sumu #AMOUNT# so splatnosťou dňa #DUE_DATE#.\n\nPlatbu prosím poukážte na číslo účtu #ACCOUNT# a uveďte variabilný symbol VS: #VARIABLE_SYMBOL#\n\nĎakujeme\n\nS pozdravom,',
    },
    settlement_invoice: {
      subject: '#MY_COMPANY# - Vyúčtovacia faktúra #NUMBER#',
      body: 'Dobrý deň,\n\nv prílohe zasielame vyúčtovaciu faktúru číslo #NUMBER# na sumu #AMOUNT#.\n\nS pozdravom,',
    },
    invoice_from_proforma: {
      subject: '#MY_COMPANY# - Faktúra #NUMBER#',
      body: 'Dobrý deň,\n\nv prílohe tohto mailu vám posielame ostrú faktúru číslo #NUMBER# k zálohovej faktúre #PROFORMA_NUMBER#.\n\nĎakujeme\n\nS pozdravom,',
    },
    credit_note: {
      subject: '#MY_COMPANY# - Dobropis #NUMBER#',
      body: 'Dobrý deň,\n\nv prílohe zasielame dobropis číslo #NUMBER#.\n\nS pozdravom,',
    },
    proforma: {
      subject: '#MY_COMPANY# - Zálohová faktúra #NUMBER#',
      body: 'Dobrý deň,\n\nv prílohe zasielame zálohovú faktúru číslo #NUMBER# na sumu #AMOUNT#.\n\nS pozdravom,',
    },
    quote: {
      subject: '#MY_COMPANY# - Cenová ponuka #NUMBER#',
      body: 'Dobrý deň,\n\nv prílohe zasielame cenovú ponuku číslo #NUMBER# platnú do #VALID_UNTIL#.\n\nS pozdravom,',
    },
    delivery_note: {
      subject: '#MY_COMPANY# - Dodací list #NUMBER#',
      body: 'Dobrý deň,\n\nv prílohe zasielame dodací list číslo #NUMBER#.\n\nS pozdravom,',
    },
    order_received: {
      subject: '#MY_COMPANY# - Prijatá objednávka #NUMBER#',
      body: 'Dobrý deň,\n\npotvrdzujeme prijatie objednávky číslo #NUMBER#.\n\nS pozdravom,',
    },
    order_issued: {
      subject: '#MY_COMPANY# - Vydaná objednávka #NUMBER#',
      body: 'Dobrý deň,\n\nv prílohe zasielame objednávku číslo #NUMBER#.\n\nS pozdravom,',
    },
    reminder_sms: {
      subject: '',
      body: 'Pripomienka: faktúra #NUMBER# na sumu #AMOUNT#, splatnosť #DUE_DATE#. #MY_COMPANY#',
    },
    reminder_email: {
      subject: 'Pripomienka - Faktúra #NUMBER#',
      body: 'Dobrý deň,\n\npripomíname neuhradenú faktúru číslo #NUMBER# na sumu #AMOUNT# so splatnosťou #DUE_DATE#.\n\nS pozdravom,',
    },
    dunning_sms: {
      subject: '',
      body: 'Upomienka: faktúra #NUMBER# na sumu #AMOUNT#, splatnosť #DUE_DATE#. #MY_COMPANY#',
    },
    dunning_email: {
      subject: 'Upomienka - Faktúra #NUMBER#',
      body: 'Dobrý deň,\n\nv našom systéme evidujeme neuhradenú faktúru číslo #NUMBER# na sumu #AMOUNT# so splatnosťou dňa #DUE_DATE#.\n\nPlatbu prosím poukážte na číslo účtu #ACCOUNT# a uveďte variabilný symbol VS: #VARIABLE_SYMBOL#\n\nĎakujeme\n\nS pozdravom,',
    },
    thank_you: {
      subject: 'Poďakovanie - Faktúra #NUMBER#',
      body: 'Dobrý deň,\n\nďakujeme za úhradu faktúry číslo #NUMBER#.\n\nS pozdravom,',
    },
  },
  en: {
    invoice: {
      subject: '#MY_COMPANY# - Invoice #NUMBER#',
      body: 'Hello,\n\nPlease find attached invoice #NUMBER# for #AMOUNT#, due on #DUE_DATE#.\n\nPlease send payment to account #ACCOUNT# and use variable symbol VS: #VARIABLE_SYMBOL#\n\nThank you\n\nBest regards,',
    },
    settlement_invoice: {
      subject: '#MY_COMPANY# - Settlement invoice #NUMBER#',
      body: 'Hello,\n\nPlease find attached settlement invoice #NUMBER# for #AMOUNT#.\n\nBest regards,',
    },
    invoice_from_proforma: {
      subject: '#MY_COMPANY# - Invoice #NUMBER#',
      body: 'Hello,\n\nPlease find attached final invoice #NUMBER# related to proforma #PROFORMA_NUMBER#.\n\nThank you\n\nBest regards,',
    },
    credit_note: {
      subject: '#MY_COMPANY# - Credit note #NUMBER#',
      body: 'Hello,\n\nPlease find attached credit note #NUMBER#.\n\nBest regards,',
    },
    proforma: {
      subject: '#MY_COMPANY# - Proforma invoice #NUMBER#',
      body: 'Hello,\n\nPlease find attached proforma invoice #NUMBER# for #AMOUNT#.\n\nBest regards,',
    },
    quote: {
      subject: '#MY_COMPANY# - Quote #NUMBER#',
      body: 'Hello,\n\nPlease find attached quote #NUMBER# valid until #VALID_UNTIL#.\n\nBest regards,',
    },
    delivery_note: {
      subject: '#MY_COMPANY# - Delivery note #NUMBER#',
      body: 'Hello,\n\nPlease find attached delivery note #NUMBER#.\n\nBest regards,',
    },
    order_received: {
      subject: '#MY_COMPANY# - Order received #NUMBER#',
      body: 'Hello,\n\nwe confirm receipt of order #NUMBER#.\n\nBest regards,',
    },
    order_issued: {
      subject: '#MY_COMPANY# - Purchase order #NUMBER#',
      body: 'Hello,\n\nPlease find attached purchase order #NUMBER#.\n\nBest regards,',
    },
    reminder_sms: {
      subject: '',
      body: 'Reminder: invoice #NUMBER# for #AMOUNT#, due #DUE_DATE#. #MY_COMPANY#',
    },
    reminder_email: {
      subject: 'Reminder - Invoice #NUMBER#',
      body: 'Hello,\n\nthis is a reminder for unpaid invoice #NUMBER# for #AMOUNT#, due on #DUE_DATE#.\n\nBest regards,',
    },
    dunning_sms: {
      subject: '',
      body: 'Dunning notice: invoice #NUMBER# for #AMOUNT#, due #DUE_DATE#. #MY_COMPANY#',
    },
    dunning_email: {
      subject: 'Dunning notice - Invoice #NUMBER#',
      body: 'Hello,\n\nour records show unpaid invoice #NUMBER# for #AMOUNT#, due on #DUE_DATE#.\n\nPlease send payment to account #ACCOUNT# and use variable symbol VS: #VARIABLE_SYMBOL#\n\nThank you\n\nBest regards,',
    },
    thank_you: {
      subject: 'Thank you - Invoice #NUMBER#',
      body: 'Hello,\n\nthank you for paying invoice #NUMBER#.\n\nBest regards,',
    },
  },
  es: {
    invoice: {
      subject: '#MY_COMPANY# - Factura #NUMBER#',
      body: 'Hola,\n\nadjuntamos la factura n.º #NUMBER# por importe #AMOUNT# con vencimiento el #DUE_DATE#.\n\nRealice el pago en la cuenta #ACCOUNT# con símbolo variable VS: #VARIABLE_SYMBOL#\n\nGracias\n\nSaludos,',
    },
    settlement_invoice: {
      subject: '#MY_COMPANY# - Factura de liquidación #NUMBER#',
      body: 'Hola,\n\nadjuntamos la factura de liquidación n.º #NUMBER# por importe #AMOUNT#.\n\nSaludos,',
    },
    invoice_from_proforma: {
      subject: '#MY_COMPANY# - Factura #NUMBER#',
      body: 'Hola,\n\nadjuntamos la factura definitiva n.º #NUMBER# correspondiente a la proforma #PROFORMA_NUMBER#.\n\nGracias\n\nSaludos,',
    },
    credit_note: {
      subject: '#MY_COMPANY# - Nota de crédito #NUMBER#',
      body: 'Hola,\n\nadjuntamos la nota de crédito n.º #NUMBER#.\n\nSaludos,',
    },
    proforma: {
      subject: '#MY_COMPANY# - Factura proforma #NUMBER#',
      body: 'Hola,\n\nadjuntamos la factura proforma n.º #NUMBER# por importe #AMOUNT#.\n\nSaludos,',
    },
    quote: {
      subject: '#MY_COMPANY# - Presupuesto #NUMBER#',
      body: 'Hola,\n\nadjuntamos el presupuesto n.º #NUMBER# válido hasta #VALID_UNTIL#.\n\nSaludos,',
    },
    delivery_note: {
      subject: '#MY_COMPANY# - Albarán #NUMBER#',
      body: 'Hola,\n\nadjuntamos el albarán n.º #NUMBER#.\n\nSaludos,',
    },
    order_received: {
      subject: '#MY_COMPANY# - Pedido recibido #NUMBER#',
      body: 'Hola,\n\nconfirmamos la recepción del pedido n.º #NUMBER#.\n\nSaludos,',
    },
    order_issued: {
      subject: '#MY_COMPANY# - Pedido emitido #NUMBER#',
      body: 'Hola,\n\nadjuntamos el pedido n.º #NUMBER#.\n\nSaludos,',
    },
    reminder_sms: {
      subject: '',
      body: 'Recordatorio: factura #NUMBER# por #AMOUNT#, vencimiento #DUE_DATE#. #MY_COMPANY#',
    },
    reminder_email: {
      subject: 'Recordatorio - Factura #NUMBER#',
      body: 'Hola,\n\nle recordamos la factura pendiente n.º #NUMBER# por importe #AMOUNT# con vencimiento el #DUE_DATE#.\n\nSaludos,',
    },
    dunning_sms: {
      subject: '',
      body: 'Reclamación: factura #NUMBER# por #AMOUNT#, vencimiento #DUE_DATE#. #MY_COMPANY#',
    },
    dunning_email: {
      subject: 'Reclamación - Factura #NUMBER#',
      body: 'Hola,\n\nconsta en nuestro sistema la factura impagada n.º #NUMBER# por importe #AMOUNT# con vencimiento el #DUE_DATE#.\n\nRealice el pago en la cuenta #ACCOUNT# con símbolo variable VS: #VARIABLE_SYMBOL#\n\nGracias\n\nSaludos,',
    },
    thank_you: {
      subject: 'Agradecimiento - Factura #NUMBER#',
      body: 'Hola,\n\ngracias por abonar la factura n.º #NUMBER#.\n\nSaludos,',
    },
  },
};

export function resolveEmailTemplateLocale(locale: string): LocaleCode {
  const code = locale.split('-')[0]?.toLowerCase() ?? 'en';
  if (code === 'sk' || code === 'es') {
    return code;
  }
  return 'en';
}

export function buildDefaultEmailTemplates(locale: string): Record<EmailTemplateKey, EmailTemplate> {
  const lang = resolveEmailTemplateLocale(locale);
  return { ...EMAIL_TEMPLATE_DEFAULTS[lang] };
}

export function mergeEmailTemplates(
  stored: Record<string, EmailTemplate> | undefined,
  locale: string,
): Record<string, EmailTemplate> {
  const defaults = buildDefaultEmailTemplates(locale);
  const merged: Record<string, EmailTemplate> = { ...defaults };

  if (!stored) {
    return merged;
  }

  for (const key of Object.keys(defaults) as EmailTemplateKey[]) {
    const entry = stored[key];
    if (!entry) {
      continue;
    }
    const fallback = defaults[key];
    merged[key] = {
      subject: entry.subject?.trim() ? entry.subject : fallback.subject,
      body: entry.body?.trim() ? entry.body : fallback.body,
    };
  }

  return merged;
}
