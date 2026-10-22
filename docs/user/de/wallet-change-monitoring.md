---
title: "Wallet-Wechsel durch Rechnungsüberwachung blockiert"
category: wallet-connection
order: 8
meta_description: "Warum ein neuer Store eine automatische Prüfrechnung haben kann und wann ein Wallet-Wechsel erneut versucht werden kann."
---

Satflux kann bei der Prüfung der empfangenden Wallet automatisch eine kleine
Lightning-Rechnung erstellen, auch vor Ihrer ersten Kundenrechnung.
Sie ist nicht zur Zahlung bestimmt und wird nach dem Auslesen archiviert.

**Archivieren beendet weder die Überwachung noch eine Lightning-Zahlungsanforderung.**
Solange eine Rechnung überwacht oder eine Zahlung verarbeitet wird, bleibt der
Wallet-Wechsel gesperrt.

Neue Prüfungen fordern eine Minute Gültigkeit und eine weitere Minute Überwachung
an. Ältere Prüfungen können die längere Frist des Stores behalten, normalerweise
etwa einen Tag. Maßgeblich sind die tatsächliche BTCPay-Frist und der Zahlungsstatus;
eine Freigabe nach zwei Minuten ist nicht für jeden Store garantiert.

Öffnen Sie die Rechnungen des betroffenen Stores in BTCPay und schließen Sie
archivierte Rechnungen ein. Prüfen Sie Status und Überwachungsende, sofern angezeigt.
Warten Sie das Ende ab und versuchen Sie den Wechsel erneut. Laufende Zahlungen,
ungeklärte Wallet-Änderungen oder eine Cashu-Wiederherstellung können länger blockieren.
Bleibt die Ursache unklar, kontaktieren Sie den Support. Löschen Sie keine Einträge
und markieren Sie keine Rechnungen als bezahlt, um die Sperre zu umgehen.

Teilen Sie dem Support nur Anzahl, Status und Fristen mit. Zahlungsanforderungen,
Zahlungslinks, Kennungen, Zugangsdaten und Kundendaten bleiben vertraulich.
