# ChannelParameter

[![Version](https://img.shields.io/badge/Symcon_Version-5.1>-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Version](https://img.shields.io/badge/Modul_Version-1.00-blue.svg)
![Version](https://img.shields.io/badge/Modul_Build-1-blue.svg)
![Version](https://img.shields.io/badge/Code-PHP-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)  

![Logo](../imgs/ubs3_logo.png)  

Ein Projekt von Ulrich Bittner - Smart System Solutions  

Dieses Modul ermöglicht eine Überwachung unterschiedlichster Kanalparameter (Variablen) von [HomeMatic](https://www.homematic.com/) und [homematic IP](https://www.homematic-ip.com/start.html) Geräten in [IP-Symcon](https://www.symcon.de).

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

* Kanalparameterüberwachung durch eine Variable
* Bei Auslösung können individuelle Aktionen festgesetzt werden:
  * Push-Nachrichten verschicken
  * E-Mail Nachrichten verschicken
  * Variablen schalten
  * Skripte ausführen
  
### 2. Voraussetzungen

- IP-Symcon ab Version 5.1

### 3. Software-Installation

- Über das Modul-Control folgende URL hinzufügen: `https://github.com/ubittner/SymconHomematicMonitoring.git`

### 4. Einrichten der Instanzen in IP-Symcon

- Unter "Instanz hinzufügen" ist das 'Homematic ChannelParameter Monitoring'-Modul unter dem Hersteller 'HomeMatic' aufgeführt

__Konfigurationsseite__:

Name                                | Beschreibung
----------------------------------- | ---------------------------------
(0) Instanzinformationen            | Informationen zu der Instanz.
(1) Kanalparameter                  | Wählen Sie den Kanalparameter aus, den Sie überwachen wollen.
(2) Überwachte Variablen            | Diese Liste beinhaltet die Variablen, welche überwacht werden sollen.
(3) Benachrichtigung                | Legen Sie die Benachrichtigungsvarianten fest.
(4) Alarmierung                     | Wenn sich der allgemeine Status ändert, können Variablen geschaltet werden oder Skripte ausgeführt werden.
(5) Verknüpfungen                   | Sie können sich eine Übersicht der überwachten Variablen anzeigen lassen oder Verknüpfungen erstellen. 
(6) Sicherung / Wiederherstellung   | Die Instanzkonfiguration kann in einem Skript gespeichert werden und wiederhergestellt werden.

___Skript___: Wenn Sie unter (6) Alarmierung ein Skript angebenen haben können während des Aufrufs folgende Systemvariablen verwendet werden:

Name                                | Beschreibung
----------------------------------- | ---------------------------------
$_IPS['Status']                     | Übergibt den Status der Gesamtstatusvariable.

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

##### Statusvariablen

Name         | Typ       | Beschreibung
------------ | --------- | ----------------
Monitoring   | Boolean   | De-/Aktiviert die Überwachung. 
Status       | Boolean   | Zeigt den Gesamtstatus der überwachten Variablen (OK/Alarm) an.
Overview     | String    | Zeigt die Übersicht der überwachten Variablen an, sofern die Option in der Instanzkonfiguration aktiviert wurde.

##### Profile:

Es werden zusätzliche Profile hinzugefügt, welche beim Löschen der Instanz automatisch entfernt werden.


### 6. WebFront

Über das WebFront kann die Überwachung de- und aktiviert werden.  
Der aktuelle Gesamtstatus wird angezeigt.

### 7. PHP-Befehlsreferenz

`HMCPM_DisplayRegisteredVariables(integer $InstanzID);`  
Listet die registrierten Variablen auf.  
`HMCPM_DisplayRegisteredVariables(12345);`

`HMCPM_ DisplayVariablesBelowThreshold(integer $InstanzID);`  
Listet die Variablen auf, deren aktueller Wert unterhalb dem Schwellenwert liegt.  
`HMCPM_ DisplayVariablesBelowThreshold(12345);`

`HMCPM_DisplayVariablesThresholdReached(integer $InstanzID);`  
Listet die Variablen auf, deren aktueller Wert den Schwellenwert erreicht, bzw. überschritten hat.  
`HMCPM_DisplayVariablesThresholdReached(12345);`
