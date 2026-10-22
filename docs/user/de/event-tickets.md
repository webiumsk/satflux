---
title: Veranstaltungstickets und Reihenfolge
category: payments
order: 20
updated: 2026-10-22
---

# Veranstaltungstickets und Reihenfolge

Veranstaltungen erstellen und bearbeiten Sie weiterhin in der Tickets-App. Titel, formatierte Beschreibung, Vor-Ort- oder Online-Typ, Ort, Termine, Bild, Währung und Kapazität bestimmen das öffentliche Angebot.

## Tickettypen sortieren

Klappen Sie eine Veranstaltung auf und öffnen Sie die Tickettypen. Aktivieren Sie die Bearbeitung der Reihenfolge, ziehen Sie einen Typ oder verwenden Sie die Auf-/Ab-Schaltflächen und speichern Sie. Abbrechen verwirft den Entwurf. Auch inaktive Typen gehören zur Reihenfolge; das öffentliche Angebot lässt sie aus und behält die relative Reihenfolge bei. Neue Typen werden angehängt. Preise und ausgestellte Tickets bleiben unverändert.

Dafür ist das kompatible Plugin **Satflux Tickets 2.1.0** erforderlich. Falls ein anderer Administrator die Veranstaltung inzwischen geändert hat, laden Sie vor dem erneuten Speichern die aktuelle Reihenfolge.

## Öffentlicher Verkauf

Teilen Sie den bestehenden Veranstaltungslink auf Ihrem BTCPay-Server. Besucher wählen Tickets und geben Teilnehmerdaten auf einer Seite ein; anschließend bezahlen sie in BTCPay. Ab Satflux Tickets 2.1.1 verwendet die Seite die Browsersprache und bietet eine Sprachauswahl (EN/SK/ES/DE/CS). Ab Version 2.2.0 verwenden Veranstaltungen vor Ort die Ortszeit und virtuelle Events die Besucherzeit; siehe den Abschnitt zur Zeitzone. Tickets für dieselbe E-Mail-Adresse werden gemeinsam versendet; andere Empfänger erhalten nur ihre eigenen Tickets. Behalten Sie Zugangshinweise für Online-Veranstaltungen in den Veranstaltungs- und E-Mail-Einstellungen bei.

Ab Satflux Tickets 2.1.2 wählen Sie die Sprache im Seitenkopf; die Seite wird sofort aktualisiert. Ausgewählte Tickets und unvollständige Empfängerdaten bleiben erhalten.

## Zeitzone des Events

Ab Satflux Tickets 2.2.0 zeigen Veranstaltungen vor Ort die Ortszeit mit UTC-Versatz, unabhängig vom Standort des Besuchers. Virtuelle Events verwenden die Browser-Zeitzone des Besuchers; UTC ist als Rückfall klar gekennzeichnet. Wählen Sie im bestehenden Formular die IANA-Zeitzone des Veranstaltungsorts, etwa Europe/Bratislava, und geben Sie die dortige Uhrzeit ein. Die genehmigte historische Migration ordnet bestehenden physischen Events Europe/Bratislava zu, ohne gespeicherte UTC-Zeitpunkte zu ändern. Eine reine Zonenänderung verschiebt den Termin nicht. Nicht vorhandene oder mehrdeutige Uhrzeiten bei einer Zeitumstellung müssen vor dem Speichern korrigiert werden. Ältere Plugins benötigen ein Update; bei Fehlern bleibt der Entwurf erhalten.

Bei einem neuen Event vor Ort wählt Satflux die gespeicherte Zeitzone des Shops vor, sofern sie unterstützt wird. Im selben Formular können Sie eine andere Ortszeitzone wählen. Diese wird am Event gespeichert; spätere Änderungen der Shop-Zeitzone ändern bestehende Events nicht. Wählen Sie die Ortszeitzone direkt in BTCPay ausdrücklich aus.
