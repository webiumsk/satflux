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

Mit CashuMelt **1.4.0.5 oder neuer** unterscheidet die Übersicht fehlgeschlagene
Zahlungen, andere unvollständige Zahlungen und abgeschlossene Datensätze mit
gespeicherten Wiederherstellungsdaten. Die Gesamtzahl kann die fehlgeschlagenen
Zeilen übersteigen. Betroffene Zeilen zeigen den Sperrgrund und den nächsten
Prüfschritt. Bei älteren Plugins bleibt diese Aufschlüsselung unbekannt.

Ein Fehler der Statusabfrage beim Mint, etwa HTTP 400, bestätigt weder Zahlung noch
Nichtzahlung. Ein erneuter Versuch kann unterstützte gespeicherte Daten nutzen;
der Fehler allein belegt keine unbezahlte Zahlung. Sind beim ursprünglichen Mint
weder Status noch nutzbare Wiederherstellungsdaten verfügbar, ist eine Klärung mit
dem Mint-Betreiber nötig. Die folgende Restguthaben-Wiederherstellung löst diese
separaten Zahlungsverpflichtungen nicht.

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

## Lightning-Checkout und Cashu-Verfügbarkeit

Mit dem aktualisierten Plugin wird CashuMelt in neuen Checkouts ausgeblendet, sobald eine Lightning-Wallet konfiguriert ist, einschließlich Blink API, LN-Adressen und Boltz. Ein Lightning-Ausfall bietet Cashu nicht automatisch an. Bestehende Cashu-Quotes bleiben mit ihren ursprünglichen Einstellungen zur Wiederherstellung verfügbar.

Das erneute Aktivieren einer Cashu-Konfiguration mit identischem Mint, Einheit, Zieladresse, Vertrauensliste und Gebührenlimits ändert das Zahlungsziel nicht. Eine Änderung des Zahlungsziels bleibt bei ungeklärten Mitteln gesperrt. Auch die Kombination aus erneuter Aktivierung und Änderungen dieser Werte unterliegt weiterhin den Sicherheitsprüfungen für die Mittel. Die Aktivierung zeigt Cashu nicht neben einer konfigurierten Lightning-Wallet an.

Eine direkt über Lightning bezahlte BTCPay-Rechnung kann abgeschlossen sein, während ein separater Cashu-Quote aussteht. HTTP 400 vom Mint bestimmt nicht, ob dieser Quote finanziert wurde. Der Rechnungsstatus allein hebt seine Wiederherstellungssperre nicht auf. Beim Ändern von Wallet-Zugangsdaten erfolgt die Erkennung nach Verlassen des Feldes oder über Speichern/Testen; Tippen allein wählt keine andere Wallet.
