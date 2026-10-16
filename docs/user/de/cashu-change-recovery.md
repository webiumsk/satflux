---
title: Cashu-Restguthaben wiederherstellen
category: payments
order: 4
meta_description: Zurückgegebene Cashu-Gebührenreserven anzeigen und sicher in die eigene Wallet übernehmen.
---

# Cashu-Restguthaben wiederherstellen

CashuMelt kann ungenutzte Gebührenreserven als Cashu-Restguthaben aufbewahren. Auch
nach abgeschlossenen Zahlungen kann daher Guthaben verbleiben. Die automatische
Lightning-Auszahlung beginnt ab 100 sat geeignetem Guthaben derselben Mint; ein
kleinerer Betrag kann einen Wallet-Wechsel weiterhin blockieren.

Die Cashu-Verbindungseinstellungen zeigen die letzte Sicherheitsprüfung bereits
vor der Bearbeitung. Ist ein Wechsel blockiert, folgen Sie dem Link zu Abrechnungen
und Wiederherstellung. Beim Aktualisieren dieses Status bleiben bearbeitete
Einstellungen erhalten. Eine nicht verfügbare Prüfung bestätigt keinen sicheren
Wallet-Wechsel.

Mit CashuMelt **1.4.0.4 oder neuer** öffnen Sie die **Cashu-Abrechnungen** des Shops
und den Bereich zur Wiederherstellung. Beträge werden in Satoshi angezeigt,
Datensätze separat gezählt. Nur der registrierte Shop-Inhaber mit bestätigter
E-Mail-Adresse kann Token herunterladen oder die Wiederherstellung überprüfen.

1. Wählen Sie den Token-Download und bestätigen Sie den Hinweis. Geben Sie bei
   Aufforderung Ihr Passwort und anschließend den sechsstelligen E-Mail-Code ein.
2. Bewahren Sie `cashumelt-change.json` privat auf. Die Datei enthält ausgebbare
   Token. Importieren Sie die Token aus dem JSON-Array in Ihre eigene kompatible
   Cashu-Wallet. Senden Sie die Datei weder an den Support noch an andere Personen.
3. Wählen Sie nach dem Import die Überprüfung und bestätigen Sie diese mit einem
   neuen E-Mail-Code. Die Mint muss den Verbrauch der exportierten Token bestätigen.

Der Download allein hebt die Sperre nicht auf. Satflux aktualisiert danach Guthaben
und Sicherheitsprüfung; weitere offene Datensätze können Änderungen weiterhin
verhindern. Bei verlorenem Download oder unbekanntem Ergebnis aktualisieren Sie den
Bereich und starten einen neu bestätigten Versuch. Unklare Auszahlungen und nicht
unterstützte Einheiten können Unterstützung erfordern; ihre Datensätze bleiben erhalten.

Bei älteren Plugins wird die fehlende Unterstützung angezeigt. Eine nicht verfügbare
Prüfung bedeutet kein Nullguthaben. Der bestehende Bereich **Cashu recovery** in
BTCPay bleibt für unterstützte Wiederherstellungsaktionen verfügbar.
