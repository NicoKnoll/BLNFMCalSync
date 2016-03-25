# BLNFMCalSync

*Dependencies:*
- Events Manager (http://wp-events-plugin.com/)

Please add bugs and ideas as issues on GitHub.

## How to use custom attributes

Add the following line to "Allgemeine Optionen" -> "Veranstaltung Attribute":

``` 
#_ATT{Kurzbeschreibung} // bitte nicht mit den Inhalt des Posts selbst matchen - der soll für Redakteure editierbar sein in WP und nicht überschrieben werden.

#_ATT{Line Up} // danach Tags daraus generieren
#_ATT{Preis} //  if Preis==0 > add Kategorie “kostenlos”
#_ATT{Stil} // danach Tags daraus generieren

#_ATT{Gewinnspiel} // if set > add Kategorie “Gewinnspiel”
#_ATT{Parent} // hier müssen wir noch schauen wie sich eine Verknüpfung über related posts automatisch anlegen lässt.

#_ATT{Promoted} // if set > add Kategorie “sponsored”
#_ATT{Recommended} // if set > add Kategorie “Tipp”
#_ATT{Team} // if set > add Kategorie “Team”

#_ATT{Status}{aktiv|abgesagt|ausverkauft|verschoben} // anything else than “aktiv” add Tag mit Inhalt |abgesagt|ausverkauft ODER verschoben (und lösche ggf. das andere)
```
