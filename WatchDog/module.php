<?php

/*
 * @module      Homematic Watchdog
 *
 * @prefix      HMWDG
 *
 * @file        module.php
 *
 * @developer   Ulrich Bittner
 * @project     Ulrich Bittner
 * @copyright   (c) 2019
 * @license    	CC BY-NC-SA 4.0
 *              https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * @version     1.02-3
 * @date        2019-05-20, 18:00
 * @lastchange  2019-05-20, 18:00
 *
 * @see         https://git.ubittner.de/ubittner/HomematicMonitoring
 *
 * @guids       Library
 *              {027455D4-AD7F-446C-A00E-34ED01081A67}
 *
 *              Homematic Watchdog
 *             	{C828CCD4-009E-412F-B8F3-3032ECB2E1A9}
 *
 * @changelog   2019-01-11, 09:00, initial version 1.02-3
 *
 */

// Declare
declare(strict_types=1);

// Include
include_once __DIR__ . '/helper/HMWDG_autoload.php';

class Watchdog extends IPSModule
{
    // Helper
    use HMWDG_alerting;
    use HMWDG_backupRestore;
    use HMWDG_notifications;
    use HMWDG_variables;

    public function Create()
    {
        // Never delete this line!
        parent::Create();

        //#################### Register properties

        // Monitoring parameters
        $this->RegisterPropertyInteger("TimeBase", 0);
        $this->RegisterPropertyInteger("TimeValue", 60);
        $this->RegisterPropertyInteger("MonitoringInterval", 60);
        // Monitored variables
        $this->RegisterPropertyString('MonitoredVariables', '[]');
        // Notification
        $this->RegisterPropertyBoolean('UseNotification', false);
        $this->RegisterPropertyString('TitleDescription', $this->Translate('Homematic Watchdog'));
        $this->RegisterPropertyString('LocationDesignation', '');
        $this->RegisterPropertyString('MessageTexts', '[{"Status":false,"MessageText":"' . $this->Translate('Status update ok') . '"},{"Status":true,"MessageText":"' . $this->Translate('Status update overdue') . '"}]');
        $this->RegisterPropertyBoolean('AlwaysNotifyBelowThreshold', false);
        $this->RegisterPropertyBoolean('NotifyOnceBelowThreshold', true);
        $this->RegisterPropertyBoolean('UseMessageDayLimitBelowThreshold', false);
        $this->RegisterPropertyBoolean('AlwaysNotifyThresholdReached', false);
        $this->RegisterPropertyBoolean('NotifyOnceThresholdReached', true);
        $this->RegisterPropertyBoolean('UseMessageDayLimitThresholdReached', false);
        $this->RegisterPropertyString('WebFronts', '[]');
        $this->RegisterPropertyString('EmailRecipients', '[]');
        // Alerting
        $this->RegisterPropertyBoolean('UseAlerting', false);
        $this->RegisterPropertyString('TargetVariables', '[]');
        $this->RegisterPropertyString('TargetScripts', '[]');
        // Links
        $this->RegisterPropertyBoolean('UseOverview', false);
        $this->RegisterPropertyInteger('LinkCategory', 0);
        // Backup / Restore
        $this->RegisterPropertyInteger('BackupCategory', 0);
        $this->RegisterPropertyInteger('Configuration', 0);

        //#################### Create profiles

        // Status
        $profileName = 'HMWDG.' . $this->InstanceID . '.Status';
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 0);
        }
        IPS_SetVariableProfileAssociation($profileName, 0, 'OK', 'Information', 0x00FF00);
        IPS_SetVariableProfileAssociation($profileName, 1, 'Alarm', 'Warning', 0xFF0000);

        //#################### Register variables

        // Monitoring
        $this->RegisterVariableBoolean('Monitoring', $this->Translate('Monitoring'), '~Switch');
        $this->EnableAction('Monitoring');
        IPS_SetPosition($this->GetIDForIdent('Monitoring'), 0);

        // Status
        $this->RegisterVariableBoolean('Status', 'Status', 'HMWDG.' . $this->InstanceID . '.Status');
        IPS_SetPosition($this->GetIDForIdent('Status'), 1);

        // Last check
        $this->RegisterVariableInteger('LastCheck', $this->Translate('Last check'), '~UnixTimestamp');
        IPS_SetPosition($this->GetIDForIdent('LastCheck'), 2);
        IPS_SetIcon($this->GetIDForIdent('LastCheck'), 'Clock');

        // Last message
        $this->RegisterVariableString('LastMessage',  $this->Translate('Last message'), '~TextBox');
        IPS_SetPosition($this->GetIDForIdent('LastMessage'), 3);
        IPS_SetIcon($this->GetIDForIdent('LastMessage'), 'Database');

        // Alert view
        $this->RegisterVariableString("AlertView", $this->Translate('Active alarms'), '~HTMLBox');
        IPS_SetPosition($this->GetIDForIdent('AlertView'), 4);
        IPS_SetIcon($this->GetIDForIdent('AlertView'), 'Database');

        //#################### Register timer

        // Timer
        $this->RegisterTimer("CheckVariablesTimer", 0, 'HMWDG_CheckMonitoredVariables($_IPS[\'TARGET\']);');
        // Reset limit
        $this->RegisterTimer('ResetMessageLimit', 0, 'HMWDG_ResetMessageLimit($_IPS[\'TARGET\']);');
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();

        // Delete profiles
        $this->DeleteProfiles();
    }

    public function ApplyChanges()
    {
        // Register messages
        // Base
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        // Never delete this line!
        parent::ApplyChanges();

        // Check kernel runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        if (GetValue($this->GetIDForIdent('Monitoring'))) {
            $this->SetTimerInterval("CheckVariablesTimer", $this->ReadPropertyInteger("MonitoringInterval") * 1000);
        }

        // Set buffer
        $monitoredVariables = [];
        $variables = json_decode($this->ReadPropertyString('MonitoredVariables'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                if ($variable->UseMonitoring) {
                    array_push($monitoredVariables, $variable->ID);
                }
            }
        }
        $this->SetBuffer('VariablesBelowThreshold', json_encode($monitoredVariables));
        $this->SetBuffer('VariablesThresholdReached', json_encode([]));
        $this->SetBuffer('BlockedVariablesForTodayBelowThreshold', json_encode([]));
        $this->SetBuffer('BlockedVariablesForTodayThresholdReached', json_encode([]));

        // Reset message limit
        $this->SetResetMessageLimitTimer();

        // Check actual status
        $this->CheckMonitoredVariables();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug('MessageSink', 'SenderID: ' . $SenderID . ', Message: ' . $Message, 0);
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;
            default:
                break;
        }
    }

    /**
     * Applies changes when the kernel is ready.
     */
    private function KernelReady()
    {
        $this->ApplyChanges();
    }

    /**
     * Deletes the profiles.
     */
    private function DeleteProfiles()
    {
        $profiles = ['Status'];
        foreach ($profiles as $profile) {
            $profileName = 'HMWDG.' . $this->InstanceID . '.' . $profile;
            if (@IPS_VariableProfileExists($profileName)) {
                IPS_DeleteVariableProfile($profileName);
            }
        }
    }

    //#################### Request action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Monitoring':
                $this->ToggleMonitoring($Value);
                break;
        }
    }

    /**
     * Toggles the monitoring switch.
     *
     * @param bool $State
     */
    public function ToggleMonitoring(bool $State)
    {
        if ($State) {
            // When activating the simulation, fetch actual data for a day and activate timer for updating variables
            $this->CheckMonitoredVariables();
            $this->SetTimerInterval("CheckVariablesTimer", $this->ReadPropertyInteger("MonitoringInterval") * 1000);
        } else {
            // When deactivating the simulation, kill data for simulation and deactivate timer for updating variables
            $this->SetTimerInterval("CheckVariablesTimer", 0);
            $this->SetValue('AlertView', $this->Translate('Watchdog disabled'));
        }
        $this->SetValue('Monitoring', $State);
    }
}
