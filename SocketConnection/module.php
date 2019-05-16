<?php

/*
 * @module      Homematic Socket Connection Monitoring
 *
 * @description Monitors the connection of the Homematic socket
 *
 * @prefix      HMSCM
 *
 * @file        module.php
 *
 * @developer   Ulrich Bittner
 * @project     Ulrich Bittner
 * @copyright   (c) 2019
 * @license     CC BY-NC-SA 4.0
 *              https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * @version     1.00-1
 * @date        2019-01-11, 09:00
 * @lastchange  2019-01-11, 09:00
 *
 * @see         https://git.ubittner.de/ubittner/HomematicMonitoring
 *
 * @guids       Library
 *              {302A78C0-FBAA-4EE7-AD4C-CD2C4C9AF99A}
 *
 *              Homematic Socket Connection Monitoring
 *             	{590AB9CB-F3EE-466E-97E0-75FF966489C0}
 *
 * @changelog   2019-01-11, 09:00, initial version 1.00-1
 *
 */

// Declare
declare(strict_types=1);

// Include
include_once __DIR__ . '/helper/HMSCM_autoload.php';

class SocketConnection extends IPSModule
{
    // Helper
    use HMSCM_alerting;
    use HMSCM_backupRestore;
    use HMSCM_connection;
    use HMSCM_notifications;

    public function Create()
    {
        // Never delete this line!
        parent::Create();

        //#################### Register properties

        // Socket connection
        $this->RegisterPropertyBoolean('UseSocketMonitoring', false);
        $this->RegisterPropertyBoolean('UseStateUpdate', false);
        $this->RegisterPropertyInteger('UpdateLimit', 3);
        // Notification
        $this->RegisterPropertyBoolean('UseNotification', false);
        $this->RegisterPropertyString('LocationDesignation', '');
        $this->RegisterPropertyString('TitleDescription', 'Homematic CCU Monitoring');
        $this->RegisterPropertyString('MessageTexts', '[{"Status":false,"MessageText":"' . $this->Translate('Lost CCU connection, please check') . '"},{"Status":true,"MessageText":"' . $this->Translate('CCU Connection established') . '"}]');
        $this->RegisterPropertyInteger('NotificationLimit', 3);
        $this->RegisterPropertyString('WebFronts', '[]');
        $this->RegisterPropertyString('EmailRecipients', '[]');
        // Alerting
        $this->RegisterPropertyBoolean('UseAlerting', false);
        $this->RegisterPropertyString('TargetVariables', '[]');
        $this->RegisterPropertyString('TargetScripts', '[]');
        // Backup / Restore
        $this->RegisterPropertyInteger('BackupCategory', 0);
        $this->RegisterPropertyInteger('Configuration', 0);

        //#################### Create profiles

        // Status
        $profileName = 'HMSCM.' . $this->InstanceID . '.Status';
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 0);
        }
        IPS_SetVariableProfileAssociation($profileName, 0, 'OK', 'Information', 0x00FF00);
        IPS_SetVariableProfileAssociation($profileName, 1, 'Alarm', 'Warning', 0xFF0000);

        //################### Register variables

        // Monitoring
        $this->RegisterVariableBoolean("Monitoring", $this->Translate("Monitoring"), "~Switch");
        $this->EnableAction("Monitoring");
        IPS_SetPosition($this->GetIDForIdent('Monitoring'), 0);
        // Status
        $this->RegisterVariableBoolean('Status', 'Status', 'HMSCM.' . $this->InstanceID . '.Status');
        IPS_SetPosition($this->GetIDForIdent('Status'), 1);
        // Last message
        $this->RegisterVariableString('LastMessage', $this->Translate('Last message'), '~TextBox');
        IPS_SetPosition($this->GetIDForIdent('LastMessage'), 2);
        IPS_SetIcon($this->GetIDForIdent('LastMessage'), 'Database');

        //#################### Register attributes

        $this->RegisterAttributeInteger('StateUpdateCycle', 0);
        $this->RegisterAttributeInteger('ConnectionLostNotificationCycle', 0);
        $this->RegisterAttributeInteger('ConnectionEstablishedNotificationCycle', 0);

        //#################### Register timer

        // Reset daily limits
        $this->RegisterTimer('ResetDailyLimits', 0, 'HMSCM_ResetDailyLimits($_IPS[\'TARGET\']);');

        //#################### Connect parent

        // Connect to Homematic socket
        $this->RegisterHomematicProperties('XXX9999990');
        $this->SetReceiveDataFilter(".*9999999999.*");
        $this->RegisterPropertyBoolean("EmulateStatus", false);
        $this->ConnectParent('{A151ECE9-D733-4FB9-AA15-7F7DD10C58AF}');
    }

    public function ApplyChanges()
    {
        // Register messages
        // Base
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        // Instance message
        $parentID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($parentID != 0 && IPS_ObjectExists($parentID)) {
            $this->RegisterMessage($parentID, IM_CHANGESTATUS);
        }

        // Never delete this line!
        parent::ApplyChanges();

        // Check kernel runlevel
        if (IPS_GetKernelRunlevel() <> KR_READY) {
            return;
        }

        // Set timer
        $this->SetResetDailyLimitsTimer();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug("MessageSink", "SenderID: " . $SenderID . ", Message: " . $Message, 0);
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;
            case IM_CHANGESTATUS:
                $this->CheckSocketConnection();
                break;
            default:
                break;
        }
    }

    /**
     * Applies changes when the kernel is ready.
     */
    protected function KernelReady()
    {
        $this->ResetValues();
        $this->ApplyChanges();
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();

        // Delete profiles
        $this->DeleteProfiles();
    }

    /**
     * Deletes the profiles.
     */
    private function DeleteProfiles()
    {
        $profiles = ['Status'];
        foreach ($profiles as $profile) {
            $profileName = 'HMSCM.' . $this->InstanceID . '.' . $profile;
            if (@IPS_VariableProfileExists($profileName)) {
                IPS_DeleteVariableProfile($profileName);
            }
        }
    }

    /**
     * Resets the values.
     */
    public function ResetValues()
    {
        $this->WriteAttributeInteger('StateUpdateCycle', 0);
        $this->WriteAttributeInteger('ConnectionLostNotificationCycle', 0);
        $this->WriteAttributeInteger('ConnectionEstablishedNotificationCycle', 0);
        // Status OK
        $this->SetValue('Status', false);
    }

    /**
     * Sets the timer for the next day to reset the limits.
     */
    public function SetResetDailyLimitsTimer()
    {
        $timestamp = strtotime('tomorrow');
        $now = time();
        $interval = ($timestamp - $now) * 1000;
        $this->SetTimerInterval('ResetDailyLimits', $interval);
    }

    /**
     * Resets the daily limits.
     */
    public function ResetDailyLimits()
    {
        // Reset attributes
        $this->WriteAttributeInteger('StateUpdateCycle', 0);
        $this->WriteAttributeInteger('ConnectionLostNotificationCycle', 0);
        $this->WriteAttributeInteger('ConnectionEstablishedNotificationCycle', 0);
        // Set timer to next interval
        $this->SetResetDailyLimitsTimer();
    }

    /**
     * Registers Homematic properties.
     *
     * @param string $Address
     */
    protected function RegisterHomematicProperties(string $Address)
    {
        $this->RegisterPropertyInteger("Protocol", 0);
        $count = @IPS_GetInstanceListByModuleID(IPS_GetInstance($this->InstanceID)["ModuleInfo"]["ModuleID"]);
        if (is_array($count)) {
            $this->RegisterPropertyString("Address", $Address . ":" . count($count));
        } else {
            $this->RegisterPropertyString("Address", $Address . ":0");
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
        $this->SetValue('Monitoring', $State);
        // Check actual status
        //$this->CheckActualStatus();
    }
}

