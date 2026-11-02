---
title: Ausgaben aus SuperFaktura importieren
category: invoicing
order: 12
updated: 2026-11-02
---

# Ausgaben aus SuperFaktura importieren

Öffnen Sie **Werkzeuge > Import** in Ihrem Unternehmen, wählen Sie **Ausgaben** und dann **Ausgaben importieren**. Der Import ist auch unter **Ausgaben > Import** verfügbar.

1. Wählen Sie den XLSX-Export aus SuperFaktura oder eine XLS-/CSV-Datei.
2. Prüfen Sie die Spaltenzuordnung. Ausstellungsdatum und Gesamtbetrag sind erforderlich.
3. Starten Sie den Import und prüfen Sie importierte und übersprungene Zeilen mit ihren Gründen.
4. **Ausgaben anzeigen** öffnet die Einträge aller Jahre.

Der aktuelle Export mit 33 Spalten wird automatisch erkannt. Originalnummern, Daten, Beträge und Zahlungssymbole bleiben erhalten. Lieferanten-, Kategorie-, Zahlungs- und Positionsangaben stehen in der internen Notiz. Es werden keine Lieferantenkontakte oder einzelnen Ausgabenpositionen angelegt.

Eine datierte, ausreichende Zahlung in der Ausgabenwährung markiert die Ausgabe als bezahlt. Teilzahlungen und Fremdwährungszahlungen bleiben zur Prüfung unbezahlt; die Zahlungsangaben bleiben erhalten. Ältere Dateien mit nur einem Zahlungsdatum behalten das bisherige Verhalten als bezahlt.

Vorhandene interne Nummern werden innerhalb des Unternehmens übersprungen. Ohne interne Nummer wird eine neue vergeben; ein erneuter Import solcher Zeilen erzeugt neue Ausgaben.

Grenzen: **10 MB, 2.000 Zeilen und 256 Spalten** je Datei. Ungültige oder zu lange Zeilen werden ohne Kürzung übersprungen. Formeln werden als Text behandelt.

Bei lokaler Rechnungsstellung wird die Datei im Browser verarbeitet und mit den bestehenden verschlüsselten Daten gespeichert. Sichern Sie Ihre Wiederherstellungsphrase. PDF-Anhänge werden separat importiert.
