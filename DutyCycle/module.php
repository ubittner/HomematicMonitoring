<?php

/*
 * @module      Homematic Duty Cycle Monitoring
 *
 * @prefix      HMDCM
 *
 * @file        module.php
 *
 * @developer   Ulrich Bittner
 * @project     Ulrich Bittner
 * @copyright   (c) 2019
 * @license     CC BY-NC-SA 4.0
 *              https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * @version     1.02-3
 * @date        2019-01-11, 09:00
 * @lastchange  2019-05-20, 18:00
 *
 * @see         https://git.ubittner.de/ubittner/HomematicMonitoring
 *
 * @guids       Library
 *              {302A78C0-FBAA-4EE7-AD4C-CD2C4C9AF99A}
 *
 *              Homematic Duty Cycle Monitoring
 *             	{40589A8F-E978-4C18-B18D-3346CA80E850}
 *
 * @changelog   2019-05-20, 18:00, update to version 1.02-3
 *              2019-05-17, 18:00, update to version 1.01-2
 *              2019-01-11, 09:00, initial version 1.00-1
 *
 */

// Declare
declare(strict_types=1);

// Include
include_once __DIR__ . '/helper/HMDCM_autoload.php';

class DutyCycle extends IPSModule
{
    // Helper
    use HMDCM_alerting;
    use HMDCM_backupRestore;
    use HMDCM_dutyCycles;
    use HMDCM_notifications;

    public function Create()
    {
        // Never delete this line!
        parent::Create();

        //#################### Register properties

        // CCU DutyCycle
        $this->RegisterPropertyBoolean('UseUpdateInterval', false);
        $this->RegisterPropertyInteger('UpdateInterval', 10);
        $this->RegisterPropertyBoolean('UseVirtualChannel', false);
        $this->RegisterPropertyInteger('VirtualChannel', 0);
        $this->RegisterPropertyInteger('ThresholdValue', 80);
        // Monitored variables
        $this->RegisterPropertyString('MonitoredVariables', '[]');
        // Notification
        $this->RegisterPropertyBoolean('UseNotification', false);
        $this->RegisterPropertyString('TitleDescription', $this->Translate('Homematic DC Monitoring'));
        $this->RegisterPropertyString('LocationDesignation', '');
        $this->RegisterPropertyString('MessageTexts', '[{"Status":false,"MessageText":"' . $this->Translate('Below threshold') . '"},{"Status":true,"MessageText":"' . $this->Translate('Threshold reached or exceeded') . '"}]');
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
        $this->RegisterPropertyInteger('LinkCategory', 0);
        // Backup / Restore
        $this->RegisterPropertyInteger('BackupCategory', 0);
        $this->RegisterPropertyInteger('Configuration', 0);

        //#################### Create profiles

        // Status
        $profileName = 'HMDCM.' . $this->InstanceID . '.Status';
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 0);
        }
        IPS_SetVariableProfileAssociation($profileName, 0, 'OK', 'Information', 0x00FF00);
        IPS_SetVariableProfileAssociation($profileName, 1, 'Alarm', 'Warning', 0xFF0000);

        // DutyCycle
        $profileName = 'HMDCM.' . $this->InstanceID . '.DutyCycle';
        // Variable type is integer with suffix %
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 1);
        }
        IPS_SetVariableProfileValues($profileName, 0, 100, 1);
        IPS_SetVariableProfileText($profileName, '', ' %');
        IPS_SetVariableProfileIcon($profileName, 'Information');

        //################### Register variables

        // Monitoring
        $this->RegisterVariableBoolean('Monitoring', $this->Translate('Monitoring'), '~Switch');
        $this->EnableAction('Monitoring');
        IPS_SetPosition($this->GetIDForIdent('Monitoring'), 0);

        // Status
        $this->RegisterVariableBoolean('Status', 'Status', 'HMDCM.' . $this->InstanceID . '.Status');
        IPS_SetPosition($this->GetIDForIdent('Status'), 1);

        //#################### Register timer

        // Update timer
        $this->RegisterTimer('Update', 0, 'HMDCM_UpdateDutyCycle($_IPS[\'TARGET\']);');

        //#################### Connect parent

        // Connect to Homematic socket
        $this->RegisterHomematicProperties('XXX9999991');
        $this->SetReceiveDataFilter('.*9999999999.*');
        $this->RegisterPropertyBoolean('EmulateStatus', false);
        $this->ConnectParent('{A151ECE9-D733-4FB9-AA15-7F7DD10C58AF}');
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

        // Maintain variables
        $this->MaintainVariable('LastMessage', $this->Translate('Last message'), 3, '~TextBox', 10, true);
        IPS_SetIcon($this->GetIDForIdent('LastMessage'), 'Database');

        // Check for changes of the monitored variables
        $this->CheckMonitoredVariables();

        // Set buffer
        $devices = [];
        $variables = json_decode($this->ReadPropertyString('MonitoredVariables'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                if ($variable->UseMonitoring) {
                    array_push($devices, $variable->ID);
                }
            }
        }
        $this->SetBuffer('VariablesBelowThreshold', json_encode($devices));
        $this->SetBuffer('VariablesThresholdReached', json_encode([]));
        $this->SetBuffer('BlockedVariablesForTodayBelowThreshold', json_encode([]));
        $this->SetBuffer('BlockedVariablesForTodayThresholdReached', json_encode([]));

        // Register messages
        $this->RegisterMonitoredVariables();

        // Set update timer
        $this->SetUpdateTimer();

        // Check actual status
        $this->CheckActualStatus();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug('MessageSink', 'SenderID: ' . $SenderID . ', Message: ' . $Message, 0);
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;
            case VM_UPDATE:
                // Virtual key as update trigger
                $virtualChannel = $this->ReadPropertyInteger('VirtualChannel');
                if ($SenderID != 0 && $SenderID === $virtualChannel) {
                    $this->UpdateDutyCycle();
                }
                // Monitored variables
                $monitoredVariables = json_decode($this->ReadPropertyString('MonitoredVariables'), true);
                if (array_search($SenderID, array_column($monitoredVariables, 'ID')) !== false) {
                    $this->TriggerAlert($SenderID);
                }
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
        $profiles = ['Status', 'DutyCycle'];
        foreach ($profiles as $profile) {
            $profileName = 'HMDCM.' . $this->InstanceID . '.' . $profile;
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
        $this->SetValue('Monitoring', $State);
        // Check actual status
        $this->CheckActualStatus();
    }

    //#################### Homematic Properties

    /**
     * Registers Homematic properties.
     *
     * @param string $Address
     */
    protected function RegisterHomematicProperties(string $Address)
    {
        $this->RegisterPropertyInteger('Protocol', 0);
        $count = @IPS_GetInstanceListByModuleID(IPS_GetInstance($this->InstanceID)['ModuleInfo']['ModuleID']);
        if (is_array($count)) {
            $this->RegisterPropertyString('Address', $Address . ':' . count($count));
        } else {
            $this->RegisterPropertyString('Address', $Address . ':0');
        }
    }
}
