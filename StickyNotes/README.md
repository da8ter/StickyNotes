# Klebezettel

![Klebezettel — über der Visualisierung](https://raw.githubusercontent.com/da8ter/images/main/Klebezettel%20Visu.png)

Klebezettel für die Kachel-Visualisierung von Symcon. Die Zettel liegen frei über der ganzen Visualisierung, auf jeder Seite und auf jedem verbundenen Bildschirm an derselben Stelle. Geschrieben wird mit der Tastatur oder mit dem Finger.

## Funktionen

- Zettel in vier Farben, frei verschiebbar und in der Größe änderbar
- Text tippen und mit der Hand schreiben oder zeichnen, auch beides auf einem Zettel
- Sechs Stiftfarben, letzter Strich zurück
- Zettelblock in einer Bildschirmecke: neuer Zettel, alle Zettel ein- und ausblenden
- Gleicher Stand auf allen Bildschirmen
- Sticker: 40 eingebaute Bilder (Herz, Stern, Daumen hoch, Kaffee, Geschenk …) und eigene Bilder aus Medienobjekten frei auf dem Brett platzieren, verschieben und in der Größe ändern
- Zettel aus Skripten ankleben und abnehmen
- Kachel mit allen Zetteln als Brett, für die Symcon-App; auf einer schmalen Kachel als Kartenstoß zum Durchblättern
- Runder Knopf für einen neuen Zettel in der Akzentfarbe der Visualisierung

## Voraussetzungen

- Symcon ab Version 8.1
- Empfohlen: Chrome oder Edge mit der [Visu-Erweiterung für Symcon](https://github.com/da8ter/VisuExtensionForSymcon) (Browser-Erweiterung mit eigenem Symcon-Modul). Mit ihr sind die Zettel sofort auf jeder Seite der Visualisierung da, und Änderungen erscheinen ohne Wartezeit.
- Ohne die Erweiterung bringt die Kachel die Zettel in die Visualisierung. Sie erscheinen dann erst, wenn die Seite mit der Kachel geöffnet wurde, und nach jedem Neuladen der Visualisierung erst, wenn sie erneut geöffnet wurde.
- In der Symcon-App bleiben die Zettel als Brett in der Kachel und lassen sich nicht frei verschieben.

## Einrichten

1. Instanz „Klebezettel“ anlegen. Ohne Erweiterung genügt das: die Seite mit der Kachel öffnen.
   Für Sticker in der Liste „Sticker“ die gewünschten Medienobjekte eintragen.
2. Mit Erweiterung: Die Visu-Erweiterung nach ihrer Anleitung installieren (Symcon-Modul mit einer Instanz „Visu-Erweiterung“ und die Erweiterung im Browser) und den Browser dort einmal koppeln.
3. Im Formular der Klebezettel unten auf „An die Visu-Erweiterung andocken“ klicken. Die Klebezettel erscheinen dann dort unter „Angedockte Module“.
4. Die Visualisierung neu laden. In der gewählten Ecke erscheint der Zettelblock.

## Bedienen

![Klebezettel — Zettel mit Stift bearbeiten](https://raw.githubusercontent.com/da8ter/images/main/Klebezettel%20Handschrift.png)

![Klebezettel — Kachel](https://raw.githubusercontent.com/da8ter/images/main/Klebezettel%20Kachel.png)

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
| Zettel oder Sticker anlegen | Plus-Knopf; sind Sticker eingerichtet, erscheint erst die Auswahl |
| Im Kartenstoß blättern | Zettel antippen |
| Zettel im Kartenstoß öffnen | Zettel gedrückt halten |

Zum Bearbeiten wird ein Zettel in der Kachel groß und mittig aufgeschlagen; er behält dabei sein Seitenverhältnis, damit Handschrift und Text genauso stehen wie in der kleinen Ansicht. Die übrigen Zettel treten so lange zurück.

**Sticker** sind Bilder auf dem Brett. 40 Alltagsmotive bringt das Modul mit (Fluent Emoji 3D von Microsoft): Herz, Stern, Daumen hoch, Erledigt, Achtung, Haus, Einkaufswagen, Kaffee, Geschenk, Geburtstagskuchen und mehr. Dazu kommen eigene Bilder aus Medienobjekten, die in der Konfiguration eingerichtet werden. Alle erscheinen als Auswahl am Plus-Knopf, neben dem gewöhnlichen Zettel; die eigenen zuerst. Ein Sticker liegt wie ein Zettel auf dem Brett: verschieben, Größe ändern, abreißen. Er trägt keinen Text und keine Handschrift. Möglich sind PNG, JPEG, GIF, WebP und BMP bis 900 kB; freigestellte Bilder (PNG mit Transparenz) wirken am besten, denn der Schatten folgt der Form. Bilder, die sich nicht verwenden lassen, nennt die Konfigurationsseite mit dem Grund und tauchen gar nicht erst in der Auswahl auf.

Auf einer schmalen Kachel liegen die Zettel als Kartenstoß übereinander statt nebeneinander: der vorderste groß in der Mitte, die übrigen schauen dahinter hervor, darunter steht die Stelle im Stoß. Ein Tipp blättert weiter, ein Tipp auf einen hinteren Zettel holt ihn nach vorn, langes Drücken öffnet den vordersten. Wird die Kachel breiter gezogen, wechselt sie von selbst zurück zum Brett. Bei einem einzigen Zettel gibt es nichts zu blättern — er füllt die Kachel und öffnet sich beim Antippen.

Ein leerer Zettel verschwindet von selbst. Die Zettel liegen immer über der Visualisierung, auch über ihren Dialogen; zum Bedienen verdeckter Kacheln die Zettel ausblenden.

## Einstellungen

| Einstellung | Bedeutung |
| --- | --- |
| Zettel über der Visualisierung zeigen | Schaltet die schwebenden Zettel ein, mit und ohne Erweiterung. Aus: Zettel nur in der Kachel. |
| Ecke des Zettelblocks | Bildschirmecke für Plus und Stapel |
| Auf Änderungen prüfen alle | Abstand, in dem ein Bildschirm Änderungen von anderen Bildschirmen holt |
| Eingebaute Sticker | Bietet die 40 mitgelieferten Bilder am Plus-Knopf an (Standard: an). Ausgeschaltet bleiben schon aufgeklebte liegen. |
| Sticker | Eigene Bilder aus Medienobjekten für die Auswahl am Plus-Knopf, optional mit Namen |

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
- Mit der Erweiterung läuft das Zettel-Skript in jedem Browser, der mit der Visu-Erweiterung gekoppelt und dort nicht gesperrt ist. Wer so einen Browser bedient, kann die Zettel lesen und ändern.

## Bildnachweis

Die eingebauten Sticker stammen aus [Fluent Emoji](https://github.com/microsoft/fluentui-emoji) von Microsoft, MIT-Lizenz (`imgs/fluent/LICENSE`).
