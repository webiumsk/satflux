---
title: Evolu-Relays verwalten
category: security
order: 7
meta_description: Verwalten Sie die verschlüsselte Synchronisierung von Rechnungsdaten über mehrere Relays.
updated: 2026-11-07
---

# Evolu-Relays verwalten

Satflux speichert Rechnungsdaten auf Ihrem Gerät und synchronisiert verschlüsselte Änderungen über Relays. Standardmäßig sind `wss://evolu.satflux.io` und `wss://evolu.eu.freedomrelay.dev` eingerichtet. Sie können weitere kompatible Relays hinzufügen, insgesamt höchstens acht Server.

1. Öffnen Sie **Kontoeinstellungen > Evolu sync**. Jede Zeile zeigt die Adresse und den aktuellen Verbindungsstatus.
2. Wählen Sie **Relay hinzufügen**, geben Sie eine sichere Adresse wie `wss://relay.example.com:8443` ein und bestätigen Sie **Relay hinzufügen**. Bestehende Relays bleiben erhalten.
3. Öffnen Sie ein Relay, um die Verbindung anzusehen und **Dieses Relay verwenden** ein- oder auszuschalten. Standard-Relays bleiben in der Liste und können deaktiviert werden. Eigene Relays können nach Bestätigung entfernt werden.
4. Sichern Sie Ihre [Wiederherstellungsphrase](/documentation/backup-and-recovery), bevor Sie Tabs schließen. Melden Sie sich auf jedem Gerät an, laden Sie die aktuellen Einstellungen, schließen Sie **alle Satflux-Tabs** und öffnen Sie Satflux erneut. Dadurch startet der bestehende Synchronisierungsworker neu; das Neuladen eines einzelnen Tabs reicht nicht.

Listenänderungen gelten für Ihr Konto. **Synchronisierung auf diesem Gerät deaktivieren** pausiert alle Relays nur auf diesem Gerät. Das Deaktivieren oder Entfernen eines Relays behält lokale Daten und löscht keine bereits auf dem Server gespeicherten Daten.

## Verbindungsstatus

**Verbunden** bedeutet, dass eine echte Synchronisierungs-WebSocket-Verbindung geöffnet ist. **Verbindung wird aufgebaut**, **Getrennt** und **Verbindungsfehler** beschreiben diese Verbindung. **Unbekannt** bedeutet, dass aktuelle Verbindungsinformationen fehlen; nach einem Update kann ein Neustart aller Tabs nötig sein. **Synchronisierung pausiert** bedeutet, dass der lokale Eigentümer oder die aktuellen Relay-Einstellungen noch nicht bestätigt sind. Melden Sie sich an und laden Sie die Kontoeinstellungen online; entsperren Sie gesperrte lokale Rechnungsdaten mit der richtigen Wiederherstellungsphrase. Deaktivierte Relays und die Gerätepause werden separat angezeigt.

**Verbindung** und **Synchronisierung** werden getrennt angezeigt. Eine **überschrittene Quote** bedeutet, dass dieses Relay Daten wegen eines Speicher- oder Dienstlimits abgelehnt hat, auch bei offener Verbindung. **Antwort empfangen** bedeutet eine beobachtete Protokollantwort; sie bestätigt weder eine vollständige Sicherung noch die erneute Übertragung zuvor abgelehnter Änderungen. Starten Sie nach einer Quotenerhöhung alle Satflux-Tabs wie oben beschrieben neu, damit der Client ausstehende Daten abgleicht. Der zuletzt erfasste Evolu-Fehler in den lokalen Statistiken kann älter sein und ist nicht der aktuelle Status einzelner Relays.

Eine offene Verbindung bestätigt weder Protokollkompatibilität noch erfolgreiche Schreibvorgänge oder eine vollständige Sicherung. Der installierte Client benötigt ein mit Evolu 7.x kompatibles Relay. Verbindungen arbeiten unabhängig; fehlende Historie wird beim Abonnieren oder erneuten Verbinden abgeglichen. Relays kopieren nicht direkt untereinander, sondern werden durch einen Client synchronisiert. Geben Sie einem neuen Relay Zeit zum Aufholen.

Adressen müssen `wss://` verwenden, ohne Anmeldedaten, Pfad, Parameter oder Fragment. Doppelte Adressen werden abgewiesen. Bestehende Einstellungen mit nur einem eigenen Relay bleiben erhalten; fügen Sie Standards bewusst über **Standard-Relays hinzufügen** hinzu. Ein ausdrücklich deaktivierter Build-Standard aktiviert keine öffentlichen Standards. Ältere HTTPS-Adressen verwenden die entsprechende WSS-Verbindung. Bei einem unsicheren WS/HTTP-Standard muss der Administrator vor Listenänderungen eine sichere Standardadresse einrichten.

## Verfügbarkeit und Wiederherstellung

Freedomrelay setzt Fair-Use-Limits pro Dateneigentümer und garantiert keine Verfügbarkeit. Die veröffentlichten Bedingungen nennen keine zahlenmäßige Speicherquote; siehe [Freedomrelay](https://freedomrelay.dev/). Der Testdienst `free.evoluhq.com` gehört nicht mehr zu den Standardrelays; ausdrücklich gespeicherte Relay-Auswahlen bleiben erhalten. Daten auf diesem Testrelay können gelöscht werden; siehe [Evolu-Relay-Dokumentation](https://www.evolu.dev/docs/relay). Bewahren Sie einen unabhängigen [Datenexport](/documentation/backup-and-recovery) und Ihre Wiederherstellungsphrase auf. **Die Wiederherstellung über ein Relay** benötigt die richtige Wiederherstellungsphrase und eine verfügbare Datenkopie; die Phrase selbst enthält keine Rechnungen. Eine **exportierte Sicherung der Rechnungsdaten** kann ohne die ursprüngliche Wiederherstellungsphrase in einem anderen Konto wiederhergestellt werden. Eine verschlüsselte Sicherung benötigt die für diese Datei gewählte Sicherungspassphrase.

Die lokale SQLite-Größe beschreibt dieses Gerät. Relay-Nutzungsstatistiken betreffen nur das erste aktivierte Relay. Beide bestätigen keine vollständige Kopie auf jedem Relay.
