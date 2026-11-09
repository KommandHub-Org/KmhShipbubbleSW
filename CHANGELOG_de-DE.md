# 1.0.3

- Das Plugin-Symbol ist jetzt das Shipbubble-Logo, mit Erlaubnis von Shipbubble verwendet.

---

# 1.0.2

- Das Plugin heißt jetzt „Shipbubble“ (vorher „Shipbubble Kurierversand“). Die Versandart behält ihren Namen „Kurierversand (Shipbubble)“.
- Kommandhub entwickelt das Plugin als Entwicklungspartner von Shipbubble; README und Markenhinweise sagen das jetzt.

---

# 1.0.1

- Adresscodes von Kundenadressen werden dauerhaft gespeichert statt 30 Tage. Shipbubble berechnet jede Adressprüfung, und die Codes laufen nie ab – Sie zahlen also einmal pro Adresse. Gespeichert wird nur ein Hash der Adresse; Groß- und Kleinschreibung oder Leerzeichen ergeben keine neue Adresse.
- Lehnt Shipbubble einen gespeicherten Adresscode bei der Preisabfrage ab, wird die Adresse einmal neu geprüft, statt dass der Checkout scheitert.

---

# 1.0.0

Erste Version.

- Live-Kurierpreise von Shipbubble im Checkout: Kunden wählen unter „Kurierversand (Shipbubble)“ einen Kurier (Logo, Lieferzeit, Preis inkl. Ihres Aufschlags). Der Preis stammt immer aus dem eigenen Shipbubble-Angebot des Plugins.
- Angebote werden pro Warenkorb und Adresse zwischengespeichert; eine geänderte Menge oder Adresse holt ein neues Angebot. Kann eine Adresse nicht angeboten werden, erklärt der Checkout den Grund und der Kunde wählt eine andere Versandart; ist Shipbubble nicht erreichbar, gilt ein optionaler Ersatzpreis und Sie erhalten eine Admin-Benachrichtigung.
- Ist Shipbubble nicht erreichbar, fragt der Checkout eine Minute lang nicht erneut an, sodass Kunden nur einmal pro Minute auf eine Zeitüberschreitung warten statt auf jeder Seite.
- Shipbubble-Karte in der Bestellung: Kurier, Preise, Status, Sendungsverfolgung, Sendungsnummer des Kuriers und Frachtbrief, mit „Sendung erstellen“, „Status aktualisieren“ und „Sendung stornieren“. Ist das Checkout-Angebot nicht mehr gültig, bestätigen Sie zuerst einen neuen Kurierpreis; der Versandpreis des Kunden ändert sich nie.
- Sendungsverfolgung: Die Shipbubble-Auftragsnummer wird als Sendungsnummer der Bestellung gespeichert und im Kundenkonto und in E-Mails mit der Shipbubble-Sendungsverfolgung verlinkt.
- Shipbubble-Webhooks halten Sendung und Lieferstatus der Bestellung aktuell (abgeholt, unterwegs, zugestellt, retourniert, storniert). Webhooks werden mit Ihrem API-Schlüssel geprüft; die Einstellungen zeigen die URL für Shipbubble.
- Fertige, aktive Flows: Kurier buchen, sobald die Bestellung bezahlt ist, Tracking-E-Mail an den Kunden, Zustell-E-Mail, Hinweise an Sie bei fehlgeschlagener Buchung und Retoure. Flow-Builder-Aktion „Shipbubble-Sendung erstellen“ und Auslöser für jeden Sendungsstatus.
- Einstellungen pro Verkaufskanal: Sandbox oder Live, Verbindungstest mit Wallet-Guthaben, Prüfung der Absenderadresse, Kuriere, Paketkategorie, Standardgewicht und -karton, Aufschlag, Ersatzpreis. Hinweis, wenn aktive Produkte kein Gewicht haben.
- Bestellberechtigungen „Shipbubble-Sendungen“ (ansehen, bearbeiten).
