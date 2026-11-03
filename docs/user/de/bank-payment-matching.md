---
title: Bankzahlungen abgleichen
category: invoicing
order: 5
meta_description: Importieren Sie Kontoauszüge und gleichen Sie Zahlungen automatisch mit Rechnungen ab.
---

# Bankzahlungen abgleichen

Der Bankabgleich ordnet eingehende Zahlungen Ihren ausgestellten Dokumenten zu, damit Sie bezahlte Rechnungen erkennen.

## Transaktionen importieren

Öffnen Sie **Zahlungen** im Unternehmen und importieren Sie einen Kontoauszug. Satflux unterstützt gängige Formate:

- **CSV**-Exporte der Bank
- **CAMT**-Dateien (ISO 20022)
- **Wise**-Kontoauszüge
- Benachrichtigungs-E-Mails einiger Banken

Doppelte Transaktionen werden erkannt und übersprungen. Sie können daher einen überlappenden Kontoauszug erneut importieren.

## Bankbenachrichtigungen per E-Mail

Verwenden Sie die unter **Zahlungen > Import** angezeigte Adresse für das ausgewählte Unternehmen. Bei lokaler Rechnungsstellung importiert das Öffnen oder Aktualisieren der Zahlungen die bereits über die Serverbrücke desselben Unternehmens verarbeiteten E-Mails in die verschlüsselten Gerätedaten. Fehlende historische Bankbenachrichtigungen werden dadurch nicht abgerufen.

## Automatischer Abgleich

Satflux ordnet eingehende Transaktionen anhand des **variablen Symbols**, des Betrags und weiterer Referenzen den Rechnungen zu. Zugeordnete Rechnungen werden als bezahlt markiert; mehrdeutige Fälle bleiben zur Bestätigung oder manuellen Zuordnung offen.

Eine neu importierte ausgehende Bank-E-Mail kann eine bestehende Ausgabe als bezahlt markieren, wenn genau eine unbezahlte Ausgabe desselben Unternehmens mit dem vollständigen positiven Betrag, der Währung und dem variablen Symbol übereinstimmt. Fehlt das variable Symbol der Ausgabe, werden die Ziffern ihrer Belegnummer verwendet. Ohne variables Banksymbol muss die Zahlungsreferenz der vollständigen Belegnummer entsprechen. Das Buchungsdatum wird zum Zahlungsdatum. Mehrdeutige Zahlungen, Teilzahlungen und Überzahlungen bleiben ohne Zuordnung. Diese Automatik für Ausgaben gilt für Bank-E-Mails; CSV und Wise behalten ihr bisheriges Verhalten. Historische doppelte Zeilen werden beim Aktualisieren nicht erneut abgeglichen; prüfen Sie sie ausdrücklich über den automatischen Abgleich des Importstapels.

Neu importierte E-Mails ohne gespeicherte ursprüngliche Zahlungsrichtung können eine Ausgabe auch beim erneuten Stapelabgleich nicht automatisch bezahlen. Ältere gespeicherte Bewegungen behalten ihr bisheriges Verhalten beim ausdrücklich gestarteten Stapelabgleich.

Läuft noch ein anderer Abgleich, warten Sie vor einem weiteren Versuch auf dessen Abschluss. Konnte das Ergebnis nicht bestätigt werden, laden Sie den Tab vor einem weiteren Versuch neu.

## Ausgaben

Ausgehende Zahlungen können Sie in **Ausgaben** umwandeln, damit der Export für die Buchhaltung vollständig bleibt.
