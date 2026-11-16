---
title: Ausgaben aus SuperFaktura importieren
category: invoicing
order: 12
updated: 2026-11-16
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


## Ausgaben suchen und filtern

Grenzen Sie die Liste mit Status und Ausstellungszeitraum ein. Anfangs wird das aktuelle Jahr angezeigt; wählen Sie alle Daten, einen voreingestellten Zeitraum oder eigene Datumsgrenzen. Unter **Mehr** finden Sie Betrag, Fälligkeit und Textsuche. Durchsucht werden interne Nummer, Lieferantenbelegnummer, Titel, variabler Verwendungszweck und Notizen.

Übernehmen Sie erweiterte Kriterien mit **Filtern**. Nutzen Sie auf dem Mobilgerät den Filterbereich. **Filter zurücksetzen** (auf Mobilgeräten **Alle löschen**) entfernt Suche und andere Kriterien. Änderungen der angewendeten Filter leeren die Auswahl. Summen und **alle auswählen** beziehen sich auf das gesamte gefilterte Ergebnis, auch auf weitere Seiten.

## Vorgaben für eine neue Ausgabe

Das variable Symbol folgt den Ziffern der Belegnummer einschließlich führender Nullen. Das Lieferdatum folgt dem Eingangsdatum. Eine manuelle Änderung des jeweiligen Vorgabewerts beendet diese Verknüpfung. Aus einem Anhang ausdrücklich extrahierte Werte bleiben erhalten; bestehende Ausgaben behalten ihre gespeicherten Werte. Ein manuell gelöschtes Lieferdatum bleibt auch nach dem Speichern und erneuten Öffnen leer.

Enthält die Belegnummer mehr als 32 Ziffern, bleibt das automatisch abgeleitete variable Symbol leer und die Belegnummer erhalten. Nach dem Kürzen der Nummer wird der Standardwert wieder übernommen, sofern Sie das Symbol nicht manuell bearbeiten.

Lokale Ausgabenänderungen warten vor dem Speichern auf einen laufenden Zahlungsabgleich. Kann der Browser Änderungen nicht koordinieren, laden Sie den Tab neu oder verwenden Sie einen aktuellen Browser. Bei einem unbestätigten Ergebnis laden Sie den Tab neu und prüfen Sie den gespeicherten Datensatz vor einem weiteren Versuch. Spätere bewusste Änderungen an einer bezahlten Ausgabe behalten deren bestehenden Zahlungsstatus bei.

Lösen Sie die Bankverknüpfung, bevor Sie Betrag oder Währung der verknüpften Ausgabe ändern. Offline-Zuordnungen werden nach der Synchronisierung erneut geprüft. Muss eine Verknüpfung geprüft werden, erscheint unter Zahlungen und bei der Ausgabe ein Hinweis. Lösen Sie die ungeklärte Bankverknüpfung vor einer erneuten Zuordnung. Andere Zuordnungen und separat erfasste Zahlungen bleiben erhalten. Verwenden Sie auf allen Geräten aktuelle Anwendungsversionen.

## Ausgabenübersicht

Die Übersicht unter **Ausgaben** umfasst alle Dokumente, die den aktuellen Filtern entsprechen, auf sämtlichen Seiten. Sie zeigt Anzahl, Gesamtsumme und Summe unbezahlter Ausgaben. Die Auswahl von Zeilen verändert die Übersicht nicht. Jede Währung hat eigene Summen.

Die Summe unbezahlter Ausgaben enthält die vollen Beträge der nicht als bezahlt markierten Dokumente. Teilzahlungen in importierten Notizen werden nicht automatisch abgezogen. Prüfen Sie diese Dokumente, bevor Sie den Wert als offenen Restbetrag verwenden.

## PDF-Anhänge einer einzelnen Ausgabe

Öffnen Sie eine Ausgabe und fügen Sie ihre PDF-Datei hinzu. Bei lokaler Rechnungsverwaltung werden Anhänge und extrahierte Felder mit Ihren verschlüsselten lokalen Daten gespeichert. Ein lokaler Anhang darf höchstens **10 MiB** groß sein. Wählen Sie im Formular zum Erstellen oder Bearbeiten **Speichern**, um vorgemerkte Anhänge zu speichern. Auf der Detailseite werden Anhänge sofort gespeichert, nachdem Sie die optionale ISDOC-Verarbeitung abgeschlossen oder übersprungen haben.

Sie können der vorübergehenden Serververarbeitung zur Erkennung eingebetteter ISDOC-Daten zustimmen. Beim Abbrechen bleibt der Anhang lokal. Wird ISDOC gefunden, bestätigen Sie die Feldextraktion separat oder behalten nur den Anhang. Die Extraktion nutzt Ihr bestehendes Kontingent und Guthaben; sie ist keine OCR. Ist das Kontingent aufgebraucht, können Sie die Datei weiterhin anhängen und die Daten manuell eingeben.

Wird das Speichern eines größeren Anhangs unterbrochen, versuchen Sie es erneut, solange die Datei noch ausgewählt ist. Entfernen Sie nach dem Neuladen den unvollständigen Anhang und wählen Sie die Originaldatei erneut aus. Schließen Sie den Upload ab oder entfernen Sie den unvollständigen Anhang, bevor Sie eine vollständige Sicherung erstellen.
