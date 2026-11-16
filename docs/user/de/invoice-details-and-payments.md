---
title: Abzugsposten, Käuferdaten und Rechnungszahlungen
category: invoicing
order: 5
updated: 2026-11-16
---

# Abzugsposten, Käuferdaten und Rechnungszahlungen

Die Rechnungsdetails zeigen Abzüge, Käuferdaten und die erfasste Zahlungsart.

## Abzugsposten

Für einen Abzug auf einer slowakischen oder tschechischen Rechnung geben Sie eine negative Menge und einen positiven Einzelpreis in einer separaten manuellen Position ein, zum Beispiel `-1 x 15,00`. Der Rechnungsbetrag muss positiv bleiben. Verwenden Sie den zutreffenden Mehrwertsteuersatz; Position und Mehrwertsteuer werden abgezogen. Eine Abzugsposition darf keine Verknüpfung zu einem Lagerartikel oder Lager haben. Trennen Sie eine vorhandene Lagerposition vor der Verwendung als Abzug vom Lager.

Abzüge mit negativer Menge und positivem Preis unterstützen PDF, E-Mail, UBL/ISDOC-Export und die Vorbereitung für eFaktúra unter den bestehenden Versandbedingungen. Negative Einzelpreise bleiben bei strukturierten Exporten nicht unterstützt. Deutsche Hybridrechnungen und externe US-Steueranbieter sind von diesem Ablauf ausgenommen.

Aus solchen Rechnungen erstellte Gutschriften behalten die Abzugsposition bei, ohne die ursprüngliche Rechnung zu ändern. Die Kombination dieser Abzüge mit separaten prozentualen Rabatten wird in strukturierten Exporten weiterhin nicht unterstützt.

Manuell eingegebene Prozentsätze unterstützen bis zu vier Nachkommastellen, zum Beispiel `8,875 %`. Gespeicherte Positionssätze, Steuerübersichten, PDF-Angaben und strukturierte Exporte behalten diese Genauigkeit bei.

## Kennnummern des Käufers

Enthält der Kontakt eine Registrierungsnummer, Steuernummer oder Umsatzsteuer-ID, erscheinen diese in der Rechnungsvorschau und im PDF. Ausgestellte Rechnungen behalten die gespeicherten Käuferdaten. Spätere Kontaktänderungen ändern eine ausgestellte Rechnung nicht.

WooCommerce übernimmt unterstützte Käuferkennnummern aus der Bestellung. Leere Bestellfelder erhalten die bereits im Kontakt gespeicherten Kennnummern. Andere Checkout-Erweiterungen benötigen möglicherweise eine zusätzliche Feldzuordnung.

Ein vor dem Browserimport automatisch versandtes PDF kann nur Käuferdaten enthalten, die dem Server aus der Bestellung vorliegen. Kennnummern aus einem ausschließlich im Browser gespeicherten Kontakt lassen sich einem bereits versandten Dokument nicht nachträglich hinzufügen.

## Erfasste Zahlungsart

Angebotene Zahlungsoptionen unterscheiden sich von der erfassten Zahlungsart. Bezahlte WooCommerce-Bestellungen behalten die Klassifizierung des übermittelten Zahlungsanbieters. Zugeordnete Bankzahlungen zeigen ihre Bankquelle, bestätigte BTCPay-Zahlungen zeigen BTCPay. Manuelle Zahlungen werden entsprechend gekennzeichnet. Ältere Rechnungen ohne gespeicherten Nachweis zeigen eine nicht angegebene Zahlungsart; die Historie wird weder geschätzt noch umgeschrieben.

## Bitcoin-Zahlungslinks

Das Ausstellen einer Rechnung erstellt keine BTCPay-Rechnung. PDF und E-Mail enthalten einen Satflux-Zahlungslink, wenn Bitcoin-Zahlung aktiviert ist. Der QR-Code öffnet die Zahlungsseite im Browser. Erst nach Bestätigung der Bitcoin- / Lightning-Zahlung durch den Kunden wird ein BTCPay-Checkout erstellt oder wiederverwendet. Auch der Aussteller kann den Checkout ausdrücklich im Rechnungsdetail erstellen.

Eine erfasste Bank- oder manuelle Zahlung verhindert weitere Zahlungen über den Satflux-Link, sobald der Status den Server erreicht. Teilzahlungen und Änderungen der Zahlungsbedingungen machen das bisherige Angebot ungültig. Im Local-first-Modus erreichen offline erfasste Änderungen den Server erst nach erneuter Verbindung des Browsers. Berücksichtigen Sie diese Verzögerung beim Bestätigen einer Bankzahlung.

Bereits verteilte direkte BTCPay-Links behalten ihr bisheriges Verhalten. Eine Bankzahlung wird nicht zur Bitcoin-Zahlung und storniert keinen bereits erstellten BTCPay-Checkout automatisch.
