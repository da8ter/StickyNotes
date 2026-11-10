# Pinnwand

Klebezettel für die Kachel-Visualisierung von Symcon. Die Zettel liegen frei über der ganzen Visualisierung, auf jeder Seite und auf jedem verbundenen Bildschirm an derselben Stelle. Geschrieben wird mit der Tastatur oder mit dem Finger.

## Funktionen

- Zettel in vier Farben, frei verschiebbar und in der Größe änderbar
- Text tippen und mit der Hand schreiben oder zeichnen, auch beides auf einem Zettel
- Drei Stiftfarben, letzter Strich zurück
- Zettelblock in einer Bildschirmecke: neuer Zettel, alle Zettel ein- und ausblenden
- Gleicher Stand auf allen Bildschirmen
- Zettel aus Skripten ankleben und abnehmen
- Kachel mit allen Zetteln als Brett, für Geräte ohne Browser-Erweiterung

## Voraussetzungen

- Symcon ab Version 8.1
- Für die frei schwebenden Zettel: Chrome oder Edge mit der Browser-Erweiterung [Visu-Erweiterung für Symcon](https://github.com/da8ter/VisuExtensionForSymcon). In der Symcon-App und in Browsern ohne Erweiterung zeigt die Kachel die Zettel.

## Einrichten

1. Instanz „Pinnwand“ anlegen.
2. Im Formular der Instanz die „Adresse für die Browser-Erweiterung“ kopieren.
3. In den Einstellungen der Erweiterung eine Verbindung anlegen: Adresse des Symcon-Servers, gefolgt von der kopierten Adresse.
4. Die Visualisierung neu laden. In der gewählten Ecke erscheint der Zettelblock.

## Bedienen

| Aktion | So geht es |
| --- | --- |
| Neuer Zettel | Plus am Zettelblock |
| Verschieben | Zettel ziehen |
| Öffnen | Zettel antippen |
| Tippen | „Aa“ |
| Mit der Hand schreiben | Stift, dann auf dem Zettel schreiben |
| Größe ändern | geöffneten Zettel an der unteren rechten Ecke ziehen |
| Farbe wechseln | Farbpunkt |
| Abreißen | Mülleimer zweimal antippen |
| Alle Zettel aus- und einblenden | Stapel am Zettelblock |

Ein leerer Zettel verschwindet von selbst. Die Zettel liegen immer über der Visualisierung, auch über ihren Dialogen; zum Bedienen verdeckter Kacheln die Zettel ausblenden.

## Einstellungen

| Einstellung | Bedeutung |
| --- | --- |
| Zettel über der Visualisierung zeigen | Schaltet die schwebenden Zettel ein. Aus: Zettel nur in der Kachel. |
| Ecke des Zettelblocks | Bildschirmecke für Plus und Stapel |
| Auf Änderungen prüfen alle | Abstand, in dem ein Bildschirm Änderungen von anderen Bildschirmen holt |

## Variablen

| Name | Typ | Bedeutung |
| --- | --- | --- |
| Zettel | Integer | Anzahl der Zettel |

## Funktionen für Skripte

```php
// Zettel ankleben; Farbe: gelb, rosa, grün, blau. Liefert die Kennung oder '' (Pinnwand voll).
$id = STICKY_Add(12345, 'Waschmaschine ist fertig', 'blau');

// Zettel abnehmen
STICKY_Remove(12345, $id);

// Alle Zettel abnehmen
STICKY_Clear(12345);

// Alle Zettel als JSON (Kennung, Text, Farbe, Zahl der Striche, Zeit)
$zettel = json_decode(STICKY_GetNotes(12345), true);
```

## Grenzen

- Höchstens 30 Zettel, 600 Zeichen Text je Zettel
- Die Adresse für die Erweiterung enthält ein Zugangswort. Wer sie kennt, kann die Zettel lesen und ändern.
