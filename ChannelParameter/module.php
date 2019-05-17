<?php

/*
 * @module      Homematic Channel Parameter Monitoring
 *
 * @prefix      HMCPM
 *
 * @file        module.php
 *
 * @developer   Ulrich Bittner
 * @project     Ulrich Bittner
 * @copyright   (c) 2019
 * @license    	CC BY-NC-SA 4.0
 *              https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * @version     1.01-2
 * @date        2019-01-11, 09:00
 * @lastchange  2019-05-17, 09:00
 *
 * @see         https://git.ubittner.de/ubittner/HomematicMonitoring
 *
 * @guids       Library
 *              {027455D4-AD7F-446C-A00E-34ED01081A67}
 *
 *              Homematic Channel Parameter Monitoring
 *             	{27124868-9BA0-46F4-A5A2-20EB14111657}
 *
 * @changelog   2019-05-17, 18:00, update to version 1.01-2
 *              2019-01-11, 09:00, initial version 1.00-1
 *
 */

// Declare
declare(strict_types=1);

// Include
include_once __DIR__ . '/helper/HMCPM_autoload.php';

class ChannelParameter extends IPSModule
{
    // Helper
    use HMCPM_alerting;
    use HMCPM_backupRestore;
    use HMCPM_channelParameters;
    use HMCPM_notifications;

    public function Create()
    {
        // Never delete this line!
        parent::Create();

        //#################### Register properties

        // Channel parameter
        $this->RegisterPropertyString('Parameter', 'PLEASE_SELECT');
        $this->RegisterPropertyString('UserDefinedParameters', '');
        $this->RegisterPropertyString('ThresholdValue', '');
        // Monitored variables
        $this->RegisterPropertyString('MonitoredVariables', '[]');
        // Notification
        $this->RegisterPropertyBoolean('UseNotification', false);
        $this->RegisterPropertyString('TitleDescription', $this->Translate('Homematic Monitoring'));
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
        $this->RegisterPropertyBoolean('UseOverview', false);
        $this->RegisterPropertyInteger('LinkCategory', 0);
        // Backup / Restore
        $this->RegisterPropertyInteger('BackupCategory', 0);
        $this->RegisterPropertyInteger('Configuration', 0);


        //#################### Create profiles

        // Status
        $profileName = 'HMCPM.' . $this->InstanceID . '.Status';
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 0);
        }
        IPS_SetVariableProfileAssociation($profileName, 0, 'OK', 'Information', 0x00FF00);
        IPS_SetVariableProfileAssociation($profileName, 1, 'Alarm', 'Warning', 0xFF0000);

        //#################### Register variables

        // Monitoring
        $this->RegisterVariableBoolean("Monitoring", $this->Translate("Monitoring"), "~Switch");
        $this->EnableAction("Monitoring");
        IPS_SetPosition($this->GetIDForIdent('Monitoring'), 0);

        // Status
        $this->RegisterVariableBoolean('Status', 'Status', 'HMCPM.' . $this->InstanceID . '.Status');
        IPS_SetPosition($this->GetIDForIdent('Status'), 1);

        //#################### Register timer

        // Reset limit
        $this->RegisterTimer('ResetMessageLimit', 0, 'HMCPM_ResetMessageLimit($_IPS[\'TARGET\']);');
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
        $this->MaintainVariable ('LastMessage', $this->Translate("Last message"), 3, '~TextBox', 2, true);
        IPS_SetIcon($this->GetIDForIdent('LastMessage'), 'Database');

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

        // Reset message limit
        $this->SetResetMessageLimitTimer();

        // Check if overview is enabled
        if ($this->ReadPropertyBoolean('UseOverview')) {
            // Register variable
            $this->RegisterVariableString('Overview', $this->Translate('Overview'), 'HTMLBox', 3);
            IPS_SetIcon($this->GetIDForIdent('Overview'), 'Database');
            // Create overview
            $this->CreateOverview();
        } else {
            $this->UnregisterVariable('Overview');
        }

        // Check actual status
        $this->CheckActualStatus();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug("MessageSink", "SenderID: " . $SenderID . ", Message: " . $Message, 0);
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;
            case VM_UPDATE:
                $this->TriggerAlert($SenderID);
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
            $profileName = 'HMCPM.' . $this->InstanceID . '.' . $profile;
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
}