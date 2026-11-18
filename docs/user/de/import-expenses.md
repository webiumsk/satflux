---
title: Ausgaben aus SuperFaktura importieren
category: invoicing
order: 12
updated: 2026-11-18
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

Direkt nach der Dateiauswahl bittet die App um Zustimmung zur vorübergehenden Serververarbeitung, um eingebettete ISDOC-Daten oder lesbaren PDF-Rechnungstext zu prüfen. Sie können den Anhang ohne Verarbeitung behalten. Der Anhang zeigt den Prüfstatus und bietet eine Wiederholung an. Werden unterstützte Daten gefunden, bestätigen Sie die Extraktion separat. Prüfung und Extraktion erfolgen vor **Speichern** im Formular; solange eine Entscheidung aussteht, bleibt Speichern gesperrt. Jede erfolgreiche Extraktion wird einmal auf Ihr bestehendes Kontingent oder Guthaben angerechnet. Es wird keine OCR durchgeführt: reine Bildscans können weiterhin angehängt und manuell erfasst werden.

Text-PDFs ergeben einen teilweise vorausgefüllten Entwurf anhand erkannter slowakischer, tschechischer oder englischer Rechnungsbezeichnungen. Text-PDFs dürfen höchstens **25 Seiten** und innerhalb des bestehenden Anhangslimits **10 MiB** umfassen. Fehlende, widersprüchliche oder nicht unterstützte Felder bleiben leer und löschen keine gespeicherten Lieferanten- oder Kontodaten. Prüfen Sie die Werte am Originalbeleg. Speichern Sie sie im Erstellungs- oder Bearbeitungsformular nach der Prüfung. Auf der Ausgabendetailseite öffnet die Textextraktion eine Vorschau; erst **Extrahierte Werte speichern** ändert die Ausgabe. **Nur Anhang behalten** lässt ihre Felder unverändert. Die bestehende ISDOC-Extraktion auf der Detailseite übernimmt Daten weiterhin nach ihrer Extraktionsbestätigung. Zahlungsdaten aus Text-PDFs müssen immer geprüft werden; sie markieren die Ausgabe niemals automatisch als bezahlt und aktivieren keinen Zahlungs-QR. Fehlt beim ausgelesenen Betrag die Währung, wählen Sie vor dem Speichern die auf der Rechnung angegebene Währung aus.

Prüfen Sie nach der Textextraktion für einen **Zahlungs-QR** die Zahlungsdaten im Erstellungs- oder Bearbeitungsformular; öffnen Sie aus dem Ausgabendetail **Bearbeiten**. Vergleichen Sie Originalbeleg und Zahlungshistorie, prüfen Sie den genauen Empfänger, die Lieferanten-IBAN, Betrag, Währung und Zahlungsreferenz und bestätigen Sie, dass der gesamte Betrag unbezahlt ist. Wählen Sie **Speichern**. **Zahlungs-QR** steht in der Ausgabenliste nur bereit, wenn die Bedingungen unter **QR-Codes für Zahlungen** unten erfüllt sind. Kontaktauswahl und Extraktion ersetzen diese Prüfung nicht. Erfasste Zahlungsnachweise oder eine Bankverknüpfung verhindern die Bestätigung; die Extraktion markiert die Ausgabe nicht als bezahlt und aktiviert den QR nicht automatisch.

Wird das Speichern eines größeren Anhangs unterbrochen, versuchen Sie es erneut, solange die Datei noch ausgewählt ist. Entfernen Sie nach dem Neuladen den unvollständigen Anhang und wählen Sie die Originaldatei erneut aus. Schließen Sie den Upload ab oder entfernen Sie den unvollständigen Anhang, bevor Sie eine vollständige Sicherung erstellen.

## Lieferantenkontakt und IBAN

Wählen Sie bei einer neuen Ausgabe optional **Lieferantenkontakt**. Seine IBAN wird in **IBAN des Lieferanten** übernommen. Fehlt sie, wird das sicher aus PDF/XML desselben Lieferanten ausgelesene Konto verwendet; sonst bleibt das Feld leer. Name und IBAN können auch manuell eingegeben werden. Ein Konto eines anderen ausgelesenen Lieferanten wird nicht übernommen.

Gespeicherte Ausgaben behalten Namen und Konto auch nach Kontaktänderungen. Manuelle Änderungen und geleerte Kontofelder bleiben beim erneuten Auslesen erhalten; ändern Sie diese bewusst im Ausgabeneditor. Die interne Notiz bestimmt keinen QR-Zahlungsempfänger.

## QR-Codes für Zahlungen

Eine unbezahlte EUR-Ausgabe mit positivem Betrag, Lieferantenname mit höchstens 70 Zeichen, gültiger Lieferanten-IBAN und bis zu zehnstelliger Zahlungsreferenz bietet **Zahlungs-QR**. Wählen Sie **PAY by square** oder **PayMe**. Prüfen Sie Empfänger, genauen Betrag und Referenz in der Banking-App. Erstellen oder Scannen markiert die Ausgabe nicht als bezahlt. Für größere genaue Beträge außerhalb des PayMe-Limits verwenden Sie PAY by square.

Ohne IBAN gibt es keinen QR. Bezahlte oder stornierte Ausgaben, Bankkonflikte, negative Gutschriften und Importe mit Prüfbedarf bieten keinen Zahlungs-QR. Eine Kontaktauswahl ersetzt die Zahlungsprüfung nicht. Öffnen Sie bei einer älteren unbezahlten ISDOC/Peppol-Ausgabe **Bearbeiten**, prüfen Sie Originalbeleg und Zahlungshistorie und bestätigen Sie den genauen Empfänger, IBAN, Betrag, Währung, Zahlungsreferenz sowie den vollständig offenen Betrag. Nach dem Speichern steht **Zahlungs-QR** in der Ausgabenliste bereit. Wenn Sie Zahlungsdaten vor dem Speichern ändern, bestätigen Sie diese erneut. Ein erfasstes Zahlungsdatum, ein nicht null betragender oder unklarer Zahlungsbetrag sowie Bankverknüpfungen verhindern diese Bestätigung. Lieferantenangaben aus Notizen werden nicht in Zahlungsfelder übernommen.

## Erhaltene Gutschrift manuell hinzufügen

Wählen Sie unter **Ausgaben** die Aktion **Neue erhaltene Gutschrift** oder die Belegart **Erhaltene Gutschrift**. Geben Sie die eigene Belegnummer und Zahlungsreferenz, den Lieferanten und einen **negativen Gesamtbetrag** ein und speichern Sie. Gutschriften bleiben gekennzeichnet in Ausgaben und verringern die Summen. Sie erzeugen keinen QR für ausgehende Zahlungen und werden nicht mit Lastschriften für Ausgaben abgeglichen.

Im Ausgabedetail gibt es auch **Erhaltene Gutschrift hinzufügen**. Ein neues Formular übernimmt Lieferant und negativen Betrag; neue Nummer und Referenz bleiben leer. Es entsteht keine dauerhafte Verknüpfung zur ursprünglichen Ausgabe. Kundengutschriften bleiben unter Belege > Gutschriften.
