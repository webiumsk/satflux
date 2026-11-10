---
title: Slowakische E-Rechnungen - aktuelle Einschränkungen
category: invoicing
order: 6
meta_description: Aktueller Stand und Einschränkungen der slowakischen eFaktura-Anbindung in Satflux.
updated: 2026-11-10
---

# Slowakische E-Rechnungen - aktuelle Einschränkungen

**Eine vollständige Anbindung von Satflux an einen Anbieter für die slowakische eFaktura wurde bisher nicht verifiziert. Wir stellen Satflux derzeit nicht als einsatzbereite Lösung für die verpflichtende slowakische E-Rechnung dar.** Eine Empfangsanbindung wird entwickelt und muss noch mit dem Anbieter verifiziert werden. Ein Fertigstellungstermin ist nicht zugesagt.

## Export und Zustellung

Satflux kann Rechnungen als **UBL/XML** exportieren. Das Herunterladen dieser Datei stellt die Rechnung weder dem Empfänger zu noch bestätigt es die Erfüllung gesetzlicher Pflichten. Ein Anbieter muss das Dokument annehmen und die erforderliche Zustellung übernehmen. Ein manueller Upload kommt nur infrage, wenn der gewählte Anbieter ihn unterstützt; dieser vollständige Ablauf wurde in Satflux bisher nicht verifiziert.

## Anbieterkonto und Kosten

Ein Anbieter kann ein eigenes Konto und einen gesondert kostenpflichtigen API-Zugang voraussetzen. Ein kostenloses Webpostfach umfasst nicht unbedingt eine Anbindung aus Satflux. Bestätigen Sie Preis, Nutzungsbedingungen und Kompatibilität mit Satflux, bevor Sie einen Anbieter bezahlen oder sich auf ihn verlassen. Die Auswahl im Portal der slowakischen Finanzverwaltung erzeugt nicht automatisch funktionierende Zugangsdaten in Satflux.

## Bestehende Einstellungen und Dokumente

Bestehende Einstellungen, der Dokumentverlauf und die Bedienelemente der Anbindung bleiben erhalten. Wenn Sie die Verbindung bereits nutzen, öffnen Sie die **E-Rechnungseinstellungen** Ihres Unternehmens, um den gespeicherten Anbieter sowie die Versand- und Empfangsoptionen zu prüfen. Bewahren Sie Ihre Zugangsdaten vertraulich auf.

**Verbindung testen** prüft die Authentifizierung. Ein erfolgreicher Test oder eine abgeschlossene Checkliste belegt weder die Annahme oder Zustellung einer Rechnung durch den Anbieter noch einen funktionierenden Empfang oder die erforderliche Meldung. Prüfen Sie Status und Nachweise bei Ihrem Anbieter.

Soweit verfügbar, zeigt die Dokumentansicht den Übergabestatus und die Dokumentreferenz des Anbieters. Im Rechnungseingang können Sie empfangene Dokumente prüfen und als Ausgaben importieren. Diese Bedienelemente beschreiben bestehende Funktionen; ihre Verfügbarkeit bestätigt keine verifizierte vollständige Anbieteranbindung.

## Planung Ihrer Rechnungsstellung

Verwenden Sie bis zur Verifizierung Ihres Ablaufs einen Dienst, der den benötigten Versand und Empfang nachweisen kann. Klären Sie Ihre konkreten Pflichten und Fristen mit der slowakischen Finanzverwaltung oder Ihrer Steuerberatung.

## Empfang mit ePostman

Sobald der Empfangspilot verfügbar ist, wählen Sie ePostman in den E-Rechnungseinstellungen des richtigen slowakischen Unternehmens. Produktions- und Testkonten sind getrennt. Schließen Sie die Aktivierung beim Anbieter ab und prüfen Sie die Empfangsregistrierung; ein erfolgreicher Verbindungstest prüft nur die Zugangsdaten. Dieser Konnektor aktiviert keinen Versand über ePostman.

Empfangene Dokumente erscheinen im Posteingang unter **Ausgaben**. Prüfen Sie das Dokument oder laden Sie das ursprüngliche XML herunter. Der Import erstellt eine Ausgabe und speichert das Original in Ihren verschlüsselten lokalen Daten. Bei einem Fehler oder einem zu großen Anhang bleibt der Eintrag zur Wiederherstellung verfügbar; eine vorhandene Ausgabe allein schließt den Import nicht ab. Gutschriften müssen als solche erkannt und verarbeitet werden und dürfen nicht unbemerkt als gewöhnliche positive Ausgaben verbucht werden.

Bis zum erfolgreichen lokalen Import bleibt das Original auf dem Server mit serverseitigen Schlüsseln verschlüsselt; dort werden auch Anbieterzugangsdaten und die ausgelesene Zusammenfassung verarbeitet. Ausstehende Originale werden nicht automatisch altersbedingt gelöscht. Nach der verifizierten lokalen Übernahme werden temporäre Serverinhalte entfernt. Dies ist kein langfristiges Satflux-Serverarchiv. Klären Sie Archivzugriff, Aufbewahrung und Export mit Ihrem Anbieter, auch nach einer Kündigung.

Halten Sie die Verbindungen der Unternehmen getrennt. Die Empfangsaktivierung für ein slowakisches Unternehmen aktiviert keine ausländische LLC. Diese Anleitung bestätigt weder einen verifizierten Empfang im realen Netzwerk noch die Bereitschaft zur Meldung.
