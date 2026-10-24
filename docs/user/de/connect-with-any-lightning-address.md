---
title: Mit einer beliebigen Lightning-Adresse verbinden
category: wallet-connection
order: 2
---

# Mit einer beliebigen Lightning-Adresse verbinden

Öffne **Wallet-Verbindung**, wähle **Lightning-Adresse** und gib `du@wallet.com` ein. Satflux prüft die **konkrete Adresse**, auch bei Blink, Blitz, Flash und Coinos. Marke und gemeinsame Domain garantieren kein LUD-21.

## Direkte Lightning-Verbindung

Unterstützt die Adresse **LUD-21**, wird sie über LNAddressConnect verbunden. Eine ältere Coinos-Adresse und eine zu Coinos v3 migrierte Adresse können selbst auf `coinos.io` unterschiedliche Fähigkeiten haben.

## Ohne LUD-21 - CashuMelt (Beta)

Liefert die Adresse eine gültige Lightning-Rechnung ohne Endpunkt zur Zahlungsbestätigung, prüft Satflux die Verfügbarkeit von CashuMelt. Es zeigt die Mint und verlangt vor dem Speichern deine ausdrückliche Zustimmung. Eine vorhandene Mint bleibt erhalten; andernfalls wird die vom Betreiber konfigurierte Standard-Mint angeboten.

Die Abwicklung hängt vom Vertrauen in die Mint und von Gebühren ab. Sie bestätigt die Lightning-Auszahlung an den Adressanbieter, nicht dessen spätere interne Überweisung, etwa auf ein Ark-Guthaben. Siehe [Cashu-Lightning-Abwicklung](/documentation/cashu-lightning-settlement).

## Wenn die Adresse nicht geprüft werden kann

Zeitüberschreitungen, ungültige Antworten, fehlende Plugins oder offene Zahlungen führen zu einer Wiederholungs- oder Verfügbarkeitsmeldung. Satflux wechselt die Wallet nicht stillschweigend. Bestehende Wallets erfordern weiterhin Bestätigung und Sicherheitsprüfungen; bestehende Rechnungen behalten ihre ursprünglichen Zahlungseinstellungen.

Weitere Methoden: [Wallet auswählen](/documentation/choosing-your-wallet). Eine vom Shop gehostete Empfangsadresse ist eine eigene Funktion: [Lightning-Adressen des Shops](/documentation/store-lightning-addresses).
