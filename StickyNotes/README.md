# Klebezettel

Klebezettel für die Kachel-Visualisierung von Symcon. Die Zettel liegen frei über der ganzen Visualisierung, auf jeder Seite und auf jedem verbundenen Bildschirm an derselben Stelle. Geschrieben wird mit der Tastatur oder mit dem Finger.

## Funktionen

- Zettel in vier Farben, frei verschiebbar und in der Größe änderbar
- Text tippen und mit der Hand schreiben oder zeichnen, auch beides auf einem Zettel
- Drei Stiftfarben, letzter Strich zurück
- Zettelblock in einer Bildschirmecke: neuer Zettel, alle Zettel ein- und ausblenden
- Gleicher Stand auf allen Bildschirmen
- Zettel aus Skripten ankleben und abnehmen
- Kachel mit allen Zetteln als Brett, für die Symcon-App; auf einer schmalen Kachel als Kartenstoß zum Durchblättern
- Runder Knopf für einen neuen Zettel in der Akzentfarbe der Visualisierung

## Voraussetzungen

- Symcon ab Version 8.1
- Empfohlen: Chrome oder Edge mit der Browser-Erweiterung [Visu-Erweiterung für Symcon](https://github.com/da8ter/VisuExtensionForSymcon). Mit ihr sind die Zettel sofort auf jeder Seite der Visualisierung da.
- Ohne die Erweiterung bringt die Kachel die Zettel in die Visualisierung. Sie erscheinen dann erst, wenn die Seite mit der Kachel geöffnet wurde, und nach jedem Neuladen der Visualisierung erst, wenn sie erneut geöffnet wurde.
- In der Symcon-App bleiben die Zettel als Brett in der Kachel und lassen sich nicht frei verschieben.

## Einrichten

1. Instanz „Klebezettel“ anlegen. Ohne Erweiterung genügt das: die Seite mit der Kachel öffnen.
2. Mit Erweiterung: Im Formular der Instanz die „Adresse für die Browser-Erweiterung“ kopieren.
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
| Im Kartenstoß blättern | Zettel antippen |
| Zettel im Kartenstoß öffnen | Zettel gedrückt halten |

Zum Bearbeiten wird ein Zettel in der Kachel groß und mittig aufgeschlagen; er behält dabei sein Seitenverhältnis, damit Handschrift und Text genauso stehen wie in der kleinen Ansicht. Die übrigen Zettel treten so lange zurück.

Auf einer schmalen Kachel liegen die Zettel als Kartenstoß übereinander statt nebeneinander: der vorderste groß in der Mitte, die übrigen schauen dahinter hervor, darunter steht die Stelle im Stoß. Ein Tipp blättert weiter, ein Tipp auf einen hinteren Zettel holt ihn nach vorn, langes Drücken öffnet den vordersten. Wird die Kachel breiter gezogen, wechselt sie von selbst zurück zum Brett. Bei einem einzigen Zettel gibt es nichts zu blättern — er füllt die Kachel und öffnet sich beim Antippen.

Ein leerer Zettel verschwindet von selbst. Die Zettel liegen immer über der Visualisierung, auch über ihren Dialogen; zum Bedienen verdeckter Kacheln die Zettel ausblenden.

## Einstellungen

| Einstellung | Bedeutung |
| --- | --- |
| Zettel über der Visualisierung zeigen | Schaltet die schwebenden Zettel ein, mit und ohne Erweiterung. Aus: Zettel nur in der Kachel. |
| Ecke des Zettelblocks | Bildschirmecke für Plus und Stapel |
| Auf Änderungen prüfen alle | Abstand, in dem ein Bildschirm Änderungen von anderen Bildschirmen holt |

## Variablen

| Name | Typ | Bedeutung |
| --- | --- | --- |
| Zettel | Integer | Anzahl der Zettel |

## Funktionen für Skripte

```php
// Zettel ankleben; Farbe: gelb, rosa, grün, blau. Liefert die Kennung oder '' (kein Platz mehr).
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
