# SocketConnection

[![Version](https://img.shields.io/badge/Symcon_Version-5.1>-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Version](https://img.shields.io/badge/Modul_Version-1.02-blue.svg)
![Version](https://img.shields.io/badge/Modul_Build-3-blue.svg)
![Version](https://img.shields.io/badge/Code-PHP-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)  

![Logo](../imgs/ubs3_logo.png)  

Ein Projekt von Ulrich Bittner - Smart System Solutions  

Dieses Modul überwacht die Erreichbarkeit einer [HomeMatic](https://www.homematic.com/) CCU in [IP-Symcon](https://www.symcon.de).

Für dieses Modul besteht kein Anspruch auf Fehlerfreiheit, Weiterentwicklung, sonstige Unterstützung oder Support.

Bevor das Modul installiert wird, sollte unbedingt ein Backup von IP-Symcon durchgeführt werden.

Der Entwickler haftet nicht für eventuell auftretende Datenverluste oder sonstige Schäden.

Der Nutzer stimmt den o.a. Bedingungen, sowie den Lizenzbedingungen ausdrücklich zu.

### Inhaltverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Überwacht die Erreichbarkeit einer Homematic CCU
* Bei Auslösung können individuelle Aktionen festgesetzt werden:
  * Push-Nachrichten verschicken
  * E-Mail Nachrichten verschicken
  * Variablen schalten
  * Skripte ausführen
* Aktualisiert den Status von Homematic / Homematic IP Geräten bei Systemstart und bei Wiederverbindung 
  
### 2. Voraussetzungen

- IP-Symcon ab Version 5.1
- Homematic CCU

### 3. Software-Installation

- Sie benötigen vom Entwickler entsprechende Zugangsdaten zur Nutzung des Moduls.  

- Über das Modul-Control folgende URL hinzufügen: `https://git.ubittner.de/ubittner/HomematicMonitoring.git`

### 4. Einrichten der Instanzen in IP-Symcon

- In IP-Symcon an beliebiger Stelle `Instanz hinzufügen` auswählen und `Homematic Socket Connection Monitoring` auswählen, welches unter dem Hersteller `UBS3` aufgeführt ist. Es wird eine Instanz angelegt, in der die Eigenschaften zur Überwachung festgelegt werden können.

__Konfigurationsseite__:

Name                                | Beschreibung
----------------------------------- | ---------------------------------
(0) Instanzinformationen            | Informationen zu der Instanz
(1) Socket Verbindung               | Konfigurationsmöglichkeiten zur Überwachung der Socket Verbindung
(2) Benachrichtigungen              | Legen Sie die Benachrichtigungsvarianten fest
(3) Alarmierungen                   | Wenn sich der allgemeine Status ändert, können Variablen geschaltet oder Skripte ausgeführt werden
(4) Sicherung / Wiederherstellung   | Die Instanzkonfiguration kann in einem Skript gespeichert und wiederhergestellt werden

___Skript___: Wenn Sie unter (3) Alarmierungen ein Skript angebenen haben, so können während des Aufrufs folgende Systemvariablen verwendet werden:

Name                                | Beschreibung
----------------------------------- | ---------------------------------
$_IPS['Status']                     | Übergibt den Status der `Status` Variable, false = OK, true = Alarm

### 5. Statusvariablen und Profile

Die Statusvariablen / Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

##### Statusvariablen

Name         | Typ       | Beschreibung
------------ | --------- | ----------------
Monitoring   | Boolean   | De- / Aktiviert die Überwachung
Status       | Boolean   | Zeigt den Status der Überwachung an (`OK` / `Alarm`)
LastMessage  | String    | Zeigt die letzte Meldung an

##### Profile:

Es werden zusätzliche Profile hinzugefügt, welche beim Löschen der Instanz automatisch entfernt werden.

Der Profilname beginnt mit `HMSCM` gefolgt von der InstanzID und dem Profilnamen.

### 6. WebFront

Über das WebFront kann die Überwachung de- und aktiviert werden.  
Der aktuelle Status der Überwachung wird angezeigt.

### 7. PHP-Befehlsreferenz

`HMSCM_ToggleMonitoring(integer $InstanzID, bool $Status);`  
De- und aktivert die Überwachung.  
`HMSCM_ToggleMonitoring(12345, true);`
