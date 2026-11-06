---
title: Eigenes Evolu-Relay hinzufügen
category: security
order: 7
meta_description: Verschlüsselte Rechnungsdaten über ein eigenes Relay zusätzlich zum Standard-Relay synchronisieren.
updated: 2026-11-06
---

# Eigenes Evolu-Relay hinzufügen

Bei der lokalen Rechnungsstellung kannst du ein eigenes Evolu-Relay zusätzlich zum Standard-Relay verbinden. Wiederherstellungsphrase und Verschlüsselungsschlüssel bleiben gleich. Beide Relays erhalten verschlüsselte Daten, einschließlich der Daten freigegebener Unternehmen, auf die du Zugriff hast.

1. Öffne **Kontoeinstellungen > Evolu sync**.
2. Gib den sicheren WebSocket-Ursprung deines Relays ein, zum Beispiel `wss://relay.example.com` oder `wss://relay.example.com:8443`.
3. Lass die Option zur zusätzlichen Verwendung des Standard-Relays aktiviert und speichere. Die Seite wird neu geladen, um die Verbindungen anzuwenden.
4. Bevor du einen Satflux-Tab schließt, stelle sicher, dass du deine [Wiederherstellungsphrase](/documentation/backup-and-recovery) gesichert hast. Melde dich auf jedem Gerät an und lade die aktualisierten Einstellungen. Schließe dann **alle Satflux-Tabs** und öffne Satflux erneut. Dadurch startet der vorhandene Synchronisierungsworker neu; das Neuladen eines einzelnen Tabs reicht nicht aus.

Die Adresse muss `wss://` verwenden und darf weder Benutzername, Passwort, Pfad, Parameter noch Fragment enthalten. Der Server muss das mit Satflux' Evolu-7.x-Client kompatible Evolu-Protokoll unterstützen; ein gewöhnlicher WebSocket-Server reicht nicht aus.

Bestehende Einstellungen für ein eigenes Relay verwenden weiterhin nur dieses Relay, bis du die zusätzliche Standardverbindung aktivierst. Wenn du die Option deaktivierst, wird nur dein eigenes Relay verwendet. Eine leere eigene Adresse stellt das konfigurierte Standard-Relay wieder her. Ist das Standard-Relay in der Installation deaktiviert, aktiviert ein eigenes Relay keinen öffentlichen Ersatz.

## Verfügbarkeit und Wiederherstellung

Die Verbindungen arbeiten unabhängig: Ein nicht erreichbares Relay schaltet die andere Verbindung nicht ab. Der Datenabgleich erfolgt beim Abonnieren oder erneuten Verbinden. Der Empfang von Daten über ein Relay beweist nicht, dass das andere sie bereits besitzt. Ein neu hinzugefügtes Relay braucht möglicherweise Zeit zum Aufholen.

Der Verbindungstest prüft die Erreichbarkeit, nicht die Evolu-Protokollkompatibilität oder die Vollständigkeit einer Sicherung. Die Nutzungsstatistik bezieht sich bei einer eigenen Adresse auf das primäre eigene Relay. Sie bestätigt keine vollständige Kopie auf jedem Relay.

**Synchronisierung auf diesem Gerät deaktivieren** schaltet alle Relay-Verbindungen auf diesem Gerät ab. Andere Geräte behalten ihre Einstellungen. Das Entfernen eines Relays beendet künftige Verbindungen; bereits dort gespeicherte Daten werden nicht gelöscht.

Bewahre deine [Wiederherstellungsphrase](/documentation/backup-and-recovery) sicher auf und exportiere deine Daten regelmäßig. Zur Wiederherstellung sind die richtige Phrase und eine verfügbare Datenkopie erforderlich.
