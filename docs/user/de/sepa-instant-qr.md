---
title: SEPA Instant QR (Bankzahlungen in Euro)
category: payments
order: 5
meta_description: Akzeptieren Sie neben Bitcoin auch Euroüberweisungen über einen SEPA Instant QR-Code.
---

# SEPA Instant QR (Bankzahlungen in Euro)

Mit **SEPA Instant QR** können Sie beim Bezahlen **Banküberweisungen in Euro** akzeptieren: Der Kunde scannt einen QR-Code mit seiner Banking-App und sendet eine SEPA-Echtzeitüberweisung. Diese Option ergänzt Ihre Bitcoin- und Lightning-Zahlungen.

## Einrichtung

1. Öffnen Sie Ihren Shop und wählen Sie **SEPA Instant QR**.
2. Geben Sie die Bankdaten des Kontos ein, auf dem die Euroüberweisungen eingehen sollen, und aktivieren Sie die Methode.
3. Lassen Sie Bankbenachrichtigungen optional direkt an die B-Mail-Adresse des Shops zur Prüfung senden. Aktuelle E-Mail-Formate erfordern eine manuelle Prüfung und Bestätigung.

## Prüfung von Bank-E-Mails

Eine automatische Bestätigung erfordert einen verifizierten Bankursprung sowie ein übereinstimmendes Empfängerkonto, eine Zahlungsreferenz, einen Betrag und eine Währung. Nachrichten mit fehlenden oder mehrdeutigen Nachweisen markieren eine Rechnung nicht als bezahlt. Wenn keine automatische Prüfung verfügbar ist, prüfen Sie den Eingang in Ihrer Banking-App und bestätigen Sie ihn manuell.

Der Serverbetreiber muss eine vertrauenswürdige Prüfung von Banknachrichten konfigurieren. Die Zustellung durch einen E-Mail-Anbieter allein verifiziert die absendende Bank nicht. Nicht unterstützte Nachrichtenformate und ältere Plugin-Versionen erfordern eine manuelle Prüfung. Die Bestätigungsdauer hängt von der Zustellung und Verarbeitung der Bankbenachrichtigung ab; eine sofortige Bestätigung ist nicht garantiert. FIO verwendet ein von der Bank begrenztes Abfrageintervall.

E-Mails von Tatra banka und SLSP erfordern derzeit eine manuelle Prüfung. Zahlungsreferenzen lassen sich auch bei verifiziertem Bankabsender nicht zuverlässig von Texten des Zahlers trennen. Prüfen Sie die Überweisung in Ihrer Banking-App, bevor Sie sie manuell bestätigen. Die automatische Bestätigung über Fio und NOP bleibt verfügbar.

## Wo ist meine Zahlung

Jede ausstehende oder zu prüfende Zahlung mit einer Referenz, die mit `QR-` beginnt, hat die Schaltfläche **Wo ist meine Zahlung**. Sie fragt die öffentliche Diagnose des slowakischen Finanzverwaltungsdienstes NOP für sofortige Zahlungsbenachrichtigungen ab und zeigt den Verlauf: Transaktions-ID erstellt, Bankbenachrichtigung gespeichert, der Registrierkasse zugeordnet, bereitgestellt und empfangen.

Was Sie erwarten können:

- **NOP kennt diese ID** erscheint bei Shops, die Zahlungen über NOP mit einem eKasa-Zertifikat bestätigen, sowie bei Zahlungen, die ein Bankkonto mit aktivierten Benachrichtigungen gemeldet hat (Tatra banka, SLSP).
- **NOP hat diese ID nicht gesehen** ist bei manueller Bestätigung, Bestätigung über Fio oder Bank-E-Mails zur manuellen Prüfung normal: Die Referenzen werden lokal erzeugt, daher hat NOP auch dann nichts anzuzeigen, wenn das Geld bereits eingegangen und die Rechnung bestätigt ist. Dies bedeutet nicht, dass der Kunde nicht bezahlt hat - prüfen Sie Ihr Bankkonto.
- Der Verlauf zeigt niemals, welchem Konto das Geld gutgeschrieben wurde. Prüfen Sie den Eingang in Ihrer Banking-App, bevor Sie eine Zahlung als bezahlt markieren - satflux bestätigt über diese Ansicht keine Zahlung.

Dieselben Daten zeigt [kdejemojaplatba.sk](https://www.kdejemojaplatba.sk/), ein unabhängiger Betrachter desselben Dienstes.

## Hinweise

- Für alle Konten verfügbar, einschließlich Gastkonten.
- Zahlungen gehen auf **Ihr Bankkonto** ein - dieser von Ihnen kontrollierte Zahlungsweg in Euro ist von Bitcoin getrennt.
- Ergänzt [Rechnungsstellung](/documentation/getting-started-with-invoicing) und [Zuordnung von Bankzahlungen](/documentation/bank-payment-matching), mit denen eingehende Überweisungen Rechnungen zugeordnet werden können.
