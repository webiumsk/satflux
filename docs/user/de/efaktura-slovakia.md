---
title: Slowakische E-Rechnungen - ePostman-Anbindung
category: invoicing
order: 6
meta_description: Verbinden Sie das ePostman-Konto Ihres Unternehmens, empfangen Sie E-Rechnungen und importieren Sie Originale als Ausgaben in Satflux.
updated: 2026-11-12
---

# Slowakische E-Rechnungen - ePostman-Anbindung

Die Anbindung unterstützt **Empfang und manuellen Versand**. Empfang und Import einer Rechnung und einer Gutschrift wurden im Peppol-Testnetz verifiziert. Auch der manuelle Versand beider Dokumenttypen über ePostman an Recommand wurde in diesem Testnetz bestätigt. Produktivaktivierung, gesetzliche Meldungen und Archivbedingungen müssen separat bestätigt werden. Satflux wird nicht als vollständige Lösung für die verpflichtende slowakische E-Rechnung angeboten.

## Unternehmen verbinden

Richten Sie die Verbindung für jedes Unternehmen separat ein. Die Empfangsaktivierung eines slowakischen Unternehmens aktiviert keine ausländische LLC.

1. Öffnen Sie die **E-Rechnungseinstellungen** des richtigen Unternehmens und aktivieren Sie E-Rechnungen. Wählen Sie **ePostman** und die passende Umgebung. Produktiv- und Testkonten haben getrennte Zugangsdaten; bei einem Umgebungswechsel benötigen Sie die Zugangsdaten der neuen Umgebung. Satflux trägt die Serveradresse für ePostman ein.
2. Schließen Sie die Unternehmensaktivierung bei ePostman ab. Folgen Sie für den Produktivbetrieb der [Aktivierungsanleitung des Anbieters](https://postman.slovakodata.com/help/zaciname/onboarding/) und bestätigen Sie die Empfangsregistrierung. Die Anbieterauswahl in Satflux registriert Ihr Unternehmen weder beim Anbieter noch bei der slowakischen Finanzverwaltung. Verwenden Sie für Tests ausschließlich das Testportal und Testzugangsdaten.
3. Öffnen Sie im ePostman-Portal **Nastavenia > SAPI klienti** und erstellen Sie einen eigenen Client für Satflux. Kopieren Sie dessen **Client ID** und **Client Secret** nach Satflux. Dies sind SAPI-Client-Zugangsdaten, kein REST-API-Schlüssel. Der Anbieter zeigt das Secret nur bei der Erstellung an; bewahren Sie es vertraulich auf. Siehe die [Einstellungsanleitung des Anbieters](https://postman.slovakodata.com/help/portal/nastavenia/).
4. Prüfen Sie die in Satflux angezeigte Unternehmensidentität. Die Empfangskennung wird aus den Unternehmensdaten abgeleitet. Meldet der Assistent eine fehlende DIČ, ergänzen Sie zuerst die korrekte slowakische Steuernummer in den Unternehmensdaten. Verwenden Sie Zugangsdaten dieses Unternehmens.
5. Wählen Sie **Verbindung testen**. Der Test prüft nur die Zugangsdaten. Er bestätigt weder die Empfangsregistrierung noch eine Dokumentzustellung oder die Erfüllung gesetzlicher Pflichten. Beheben Sie Fehler, bevor Sie fortfahren.
6. Lassen Sie den Empfang aktiviert und **speichern** Sie die Einstellungen. Ein erfolgreicher Test allein speichert nicht die vollständige Empfangskonfiguration. Beheben Sie Speicher- oder Synchronisierungsfehler. Nach erfolgreichem Speichern kann Satflux eingehende Dokumente im Hintergrund abrufen, auch bei geschlossenem Browser. Fordert das Formular nach dem Speichern einer geänderten Verbindung einen neuen Test an, wiederholen Sie ihn, um die gespeicherten Zugangsdaten zu prüfen.

Ist für dieselbe Verbindung bereits ein Secret gespeichert, lassen Sie das Feld leer. Geben Sie bei einem Anbieter- oder Umgebungswechsel die neuen Zugangsdaten ein und testen Sie erneut.

## Empfangene Dokumente finden und importieren

Öffnen Sie beim selben Unternehmen **Ausgaben > Empfangene E-Rechnungen**. Prüfen Sie das Dokument oder laden Sie das ursprüngliche XML herunter und importieren Sie es anschließend als Ausgabe. Gutschriften werden als Korrekturen mit dem entsprechenden Vorzeichen importiert. Ein erfolgreicher leerer Abruf bedeutet, dass keine neuen Dokumente abgerufen wurden; er bestätigt keine Zustellung einer Testrechnung.

Starten Sie einen manuellen Abruf über **Eingehende jetzt abrufen** in den E-Rechnungseinstellungen des Unternehmens. Aktualisieren Sie anschließend den Posteingang unter Ausgaben. Das Aktualisieren des Posteingangs zeigt Dokumente, die Satflux bereits abgerufen hat.

Der Import speichert das Original in Ihren verschlüsselten lokalen Daten. Schlägt der Import fehl oder ist das Original zu groß für einen lokalen Anhang, bleibt der Eintrag zur Wiederherstellung und zum Herunterladen des Originals verfügbar. Eine vorhandene Ausgabe allein schließt den Import nicht ab.

## Originale und Anbieterkosten

Bis zum verifizierten lokalen Import bleibt das Original auf dem Server mit serverseitigen Schlüsseln verschlüsselt. Dort werden auch Anbieterzugangsdaten und die ausgelesene Dokumentzusammenfassung verarbeitet. Ausstehende Originale werden nicht automatisch altersbedingt gelöscht. Nach der verifizierten lokalen Übernahme werden temporäre Serverinhalte entfernt. Dies ist kein langfristiges Satflux-Serverarchiv. Klären Sie Archivzugriff, Aufbewahrung und Export mit dem Anbieter, auch nach einer Kündigung.

Ein Anbieterkonto kann gesonderte API-Gebühren oder Nutzungsbedingungen haben. Ein kostenloses Webpostfach umfasst nicht unbedingt den Zugriff aus Satflux. Bestätigen Sie vor dem Produktivbetrieb den passenden Tarif, den SAPI-Zugang und die Archivbedingungen mit ePostman.

## Manueller Versand und gesetzliche Pflichten

Für diesen Inlandsversand müssen beide Parteien in der Slowakei ansässig sein und `0245:<DIČ>` mit ihrer eigenen zehnstelligen DIČ verwenden.

Diese erste Versandversion lehnt Dokumente mit Positions- oder Rechnungsrabatten, Präzisionsverlust bei Preisen oder vom ausgestellten Dokument abweichenden Beträgen ab. Für solche Dokumente muss zuerst der Export erweitert werden. Teilzahlungen werden im XML-Restbetrag berücksichtigt.

Öffnen Sie für ein berechtigtes slowakisches Unternehmen mit voller Umsatzsteuerregistrierung eine ausgestellte inländische B2B-Rechnung oder Gutschrift und wählen Sie **E-Rechnung senden**. Speichern Sie zuerst die richtige Unternehmensverbindung. Aussteller und Zugangsdaten müssen zum selben Unternehmen gehören. Automatischer Versand über ePostman bleibt deaktiviert.

Eine erfolgreiche Übermittlung bedeutet **zur Verarbeitung angenommen**. Zustellung und Steuermeldung werden bei eingerichtetem Enterprise-API-Schlüssel getrennt angezeigt. Der Link zum ePostman-Portal bleibt für die passende Produktiv- oder Testumgebung verfügbar.

Bei ungewissem Ergebnis wiederholen Sie den Versuch nur, wenn Satflux dies anbietet. Dabei werden die ursprüngliche Anfrage und Kennung innerhalb des Zeitfensters des Anbieters verwendet. Nach Ablauf oder Änderungen an Dokument bzw. Verbindung klären Sie zuerst das Ergebnis mit dem Anbieter. Erstellen Sie keine neue Rechnung allein zur Wiederholung einer ungewissen Übermittlung.

## Automatischer Zustellstatus

Tragen Sie in den E-Rechnungseinstellungen desselben Unternehmens einen **ePostman-API-Schlüssel** aus **Einstellungen > API-Schlüssel** des passenden Produktiv- oder Testportals ein und speichern Sie. Er ist von SAPI Client ID und Client Secret getrennt. Satflux speichert diesen Schlüssel verschlüsselt auf seinem Server und nimmt ihn nicht in die geteilten Unternehmensdaten auf. Bewahren Sie ihn vertraulich auf. Das Entfernen des API-Schlüssels deaktiviert automatische Statusprüfungen; SAPI-Empfang und -Versand verwenden ihre bisherigen Zugangsdaten.

Satflux prüft versendete Dokumente regelmäßig, auch bei geschlossenem Browser. Das Dokumentpanel zeigt Zustellung, die **C3-MLS**-Bestätigung des Empfängers und die **C5**-Bestätigung zur Steuermeldung getrennt. Bei unterstützten Dokumenten können Sie **Status aktualisieren** verwenden; zwischen Prüfungen gilt eine kurze Wartezeit. Ein geöffnetes sichtbares Panel lädt außerdem gespeicherte Ergebnisse nach.

**UNCONFIRMED / TIMEOUT** bedeutet, dass der empfangende Zugangspunkt die Nachricht übernommen hat, die abschließende MLS-Bestätigung aber nicht rechtzeitig einging. Das allein bedeutet keinen fehlgeschlagenen Versand. Ein positives C5 ist eine vom Anbieter gemeldete Steuerbestätigung, keine geschäftliche Freigabe durch den Empfänger. Bei einem Prüffehler bleibt die letzte gültige Beobachtung zusammen mit dem Fehler sichtbar. Ersetzen Sie abgelaufene oder widerrufene API-Schlüssel in den Unternehmenseinstellungen.

Für ältere Übermittlungen sind automatische Prüfungen möglicherweise nicht verfügbar. Prüfen Sie diese Dokumente im Anbieterportal. Senden Sie eine bestehende Rechnung nicht erneut, nur um einen Status zu erhalten.

Klären Sie die konkreten Pflichten und Fristen mit der slowakischen Finanzverwaltung oder Ihrer Steuerberatung. Eine funktionierende Verbindung allein bestätigt nicht die Erfüllung aller Versand- und Meldepflichten.
