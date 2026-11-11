---
title: Zu erledigen und Migration
category: invoicing
order: 35
meta_description: Offene Aufgaben prüfen und SuperFaktura-Tabellenexporte mit bereitgestellten Originalen sicher übernehmen.
---

# Zu erledigen und Migration

## Aufgaben des Unternehmens prüfen

Öffnen Sie **Zu erledigen** in der Unternehmensnavigation. Zahlungen, Versand,
Synchronisierung und empfangene Dokumente zeigen den Grund und einen Link zum
bestehenden Bearbeitungsbereich. Aktualisieren prüft verfügbare Quellen erneut;
es versendet oder importiert keine Rechnung und bestätigt keine Zahlung.
Nicht erreichbare Quellen bleiben sichtbar.

Eine geprüfte leere Liste gilt nur für Quellen, auf die Sie zugreifen dürfen.
Lokale Zahlungszuordnungen und Versandhistorie werden auf diesem Gerät geprüft;
Synchronisierungsdiagnosen betreffen Gerät und Konto. Einige Empfangsprüfungen
erfordern den Unternehmensinhaber.

## Einen Tabellenexport übernehmen

Wählen Sie unter **Werkzeuge > Import** SuperFaktura oder eine unterstützte
allgemeine Tabelle. Exportieren Sie ausgestellte Rechnungen als Tabelle und
wählen Sie Zielunternehmen, Datei, Datumsformat und Spaltenzuordnung. Der
bestehende ISDOC-Ausgabenimport bleibt im Ausgabenimport. Weitere Anbieteradapter
stehen noch nicht bereit.

Die Vorschau prüft die gesamte unterstützte Datei und zeigt Fehler, Duplikate
sowie Summen, tatsächliche Zahlungen und Restbeträge pro Währung. Klären Sie
widersprüchliche Nummern, ungültige Werte und unklare Zahlungsangaben vor der
Bestätigung. Ein Zahlungsdatum allein belegt keine vollständige Zahlung.
Fehlende Zahlungsdaten werden nicht erfunden; Finanzdaten werden nicht überschrieben.

Optional können Sie bis zu 50 Originaldateien als PDF/XML/ISDOC hinzufügen.
Pro Datei gelten 10 MiB, für Export und Originale zusammen 20 MiB. Speicherung
und Rücklesen müssen vor der Rechnungserstellung erfolgreich sein. Die Dateien
bleiben bei Ihren lokalen Rechnungsdaten und der verschlüsselten Synchronisierung.
Sie können sie nach einem Browserneustart auf der Importseite herunterladen.

Das Archiv bewahrt den Tabellenexport und die ausgewählten Originale als
Importstapel auf.

Wählen Sie bei SuperFaktúra eine ISDOC-Datei oder ein PDF mit eingebettetem
ISDOC. Der Assistent liest die Positionen auf Ihrem Gerät und gleicht
Rechnungsnummer, Lieferant, Kunde, Datum, Währung und Betrag mit Export und
ausgewählter Firma ab. Klappen Sie die Positionen in der Vorschau vor der
Bestätigung auf. Prüfen Sie bei unlesbaren oder abweichenden Daten den Hinweis
und die betroffenen Originale. Für Rechnungen ohne geprüfte Positionen können
Sie den Import als Zusammenfassung bestätigen. Verwenden Sie das archivierte
PDF für die ursprüngliche Darstellung und die historischen Steuerangaben.

Geprüfte Abzüge bleiben als separate negative Positionen erhalten, wenn die
Positionsbeträge, die Umsatzsteuer und die Rechnungssumme übereinstimmen.

Prüfen Sie nach einer Unterbrechung das Ergebnis vor dem erneuten Import.
Abgeschlossene identische Duplikate werden übersprungen; Änderungen benötigen
Prüfung. Bewahren Sie Wiederherstellungsschlüssel und Sicherungen auf: lokale
Speicherung ist kein neues dauerhaftes Serverarchiv.

Aktualisieren Sie Satflux vor der Wiederherstellung einer Sicherung mit
Originaldateien. Ältere Versionen lehnen dieses Format ab. Ändern Sie die
ursprüngliche Sicherungsdatei nicht.

Wenn der Assistent meldet, dass die lokale Speicheraktualisierung nicht bereit
ist, schließen Sie alle Satflux-Tabs in diesem Browser und öffnen Sie die App
erneut. Erstellen Sie aus denselben Dateien eine neue Vorschau. Vollständige
exakte Duplikate werden übersprungen und geprüfte unvollständige Datensätze
können fortgesetzt werden. Bei einer fehlenden Speicherbestätigung wird auch
der betroffene Schritt angezeigt.

Eine Kontingentwarnung des Relays betrifft die Synchronisierung. Der Import
kann nach geprüftem lokalem Schreiben und Rücklesen fortfahren. Prüfen Sie
das Speicher- oder Dienstkontingent des betroffenen Relays in den
Synchronisierungseinstellungen.

Die Firmenliste zeigt geprüfte lokale Firmen während der
Relay-Synchronisierung an. Lokale Profilstatistiken laden ohne Originaldateien
und ohne auf die Relay-Auslastung zu warten. Aktualisieren kann zusätzlich
die Größe der lokalen Datenbank berechnen. Bei einer laufenden Wiederherstellung
wird zuerst die Prüfung des Kontos und der Daten abgeschlossen.


## Zaprite

Wählen Sie Zaprite unter Werkzeuge > Import. Verwenden Sie einen API-Schlüssel
mit Lesezugriff der Quellorganisation oder einen vollständigen Zaprite-JSON-Export
aus Satflux. Der Schlüssel wird nur zum Abrufen verwendet und nicht gespeichert.
Die JSON-Quelle kann bereits vor dem Import heruntergeladen werden. Prüfen Sie
Positionen, Beträge, Zahlungen und Duplikate und bestätigen Sie das Zielunternehmen.

Der Adapter unterstützt ausgewählte Fiatwährungen mit zwei Dezimalstellen und
bestätigte Zahlungen mit exakten Quelldaten. BTC, LBTC und andere Genauigkeiten
müssen ausdrücklich ausgeschlossen werden. Die Vorschau zeigt ihre Anzahl; der
vollständige JSON-Export wird trotzdem archiviert. Ausgeschlossene Rechnungen
werden nicht als Buchhaltungsdaten importiert. JSON ist ein API-Quellexport und
kein ursprüngliches Rechnungs-PDF. Prüfen Sie Entwürfe, nicht unterstützte
Anpassungen, unklare Zahlungen und widersprüchliche Rechnungsnummern zuerst
im Quellsystem.
