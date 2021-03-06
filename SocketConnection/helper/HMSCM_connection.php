<?php

// Declare
declare(strict_types=1);

trait HMSCM_connection
{
    //#################### Check socket

    /**
     * Checks the socket connection.
     */
    public function CheckSocketConnection()
    {
        if (!$this->GetValue('Monitoring')) {
            return;
        }
        if (!$this->ReadPropertyBoolean('UseSocketMonitoring')) {
            return;
        }
        $status = $this->GetValue('Status');
        // Check socket
        $parentID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($parentID != 0 && IPS_ObjectExists($parentID)) {
            $instanceState = IPS_GetInstance($parentID)['InstanceStatus'];
            $actualSocketState = false;
            if ($instanceState == 102) {
                $actualSocketState = true;
                $this->UpdateDeviceState();
            }
            // Status: 0 = OK, 1 = Alarm | Socket: 0 = lost connection, 1 = established connection
            $this->SetValue('Status', !$actualSocketState);
            // Notification and alerting, only if state is different
            if (!$actualSocketState != $status) {
                $notification = false;
                $notificationLimit = $this->ReadPropertyInteger('NotificationLimit');
                // Connection established
                if ($actualSocketState) {
                    $notificationCycle = $this->ReadAttributeInteger('ConnectionEstablishedNotificationCycle');
                    if ($notificationCycle <= $notificationLimit) {
                        $notification = true;
                        $this->WriteAttributeInteger('ConnectionEstablishedNotificationCycle', $notificationCycle + 1);
                    }
                }
                // Lost connection
                if (!$actualSocketState) {
                    $notificationCycle = $this->ReadAttributeInteger('ConnectionLostNotificationCycle');
                    if ($notificationCycle <= $notificationLimit) {
                        $notification = true;
                        $this->WriteAttributeInteger('ConnectionLostNotificationCycle', $notificationCycle + 1);
                    }
                }
                if ($notification) {
                    $this->SendNotification($actualSocketState);
                }
                $this->UpdateLastMessage($actualSocketState);
            }
            $this->ExecuteAlerting(!$actualSocketState);
        }
    }

    //#################### Update device state

    /**
     * Updates the state of all Homematic devices.
     */
    public function UpdateDeviceState()
    {
        $parentID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($parentID != 0 && IPS_ObjectExists($parentID)) {
            $state = IPS_GetInstance($parentID)['InstanceStatus'];
            if ($state == 102) {
                $updateLimit = $this->ReadPropertyInteger('UpdateLimit') - 1;
                $stateUpdateCycle = $this->ReadAttributeInteger('StateUpdateCycle');
                if ($stateUpdateCycle <= $updateLimit) {
                    $devices = IPS_GetInstanceListByModuleID('{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}');
                    foreach ($devices as $device) {
                        $children = IPS_GetChildrenIDs($device);
                        foreach ($children as $child) {
                            $object = IPS_GetObject($child);
                            if ($object['ObjectIdent'] == 'STATE' || $object['ObjectIdent'] == 'LEVEL' ) {
                                if (@HM_RequestStatus($device, $object['ObjectIdent']) == true) {
                                    $this->SendDebug('UpdateDeviceState',  IPS_GetLocation($device), 0);
                                } else {
                                    $this->LogMessage($this->Translate('Homematic device status update, Error: ') . IPS_GetLocation($device), KL_WARNING);
                                }
                            }
                        }
                    }
                    $this->WriteAttributeInteger('StateUpdateCycle', $stateUpdateCycle + 1);
                    $this->LogMessage($this->Translate('The status of all Homematic devices has been updated'), KL_NOTIFY);
                }
                if ($stateUpdateCycle > $updateLimit) {
                    $this->LogMessage($this->Translate('The limit for status update of all Homematic devices has been reached'), KL_WARNING);
                }
            }
        }
    }

    public function ShowStateUpdateCycle()
    {
        echo $this->Translate('State update: ') . "\n" . $this->ReadAttributeInteger('StateUpdateCycle');
    }
}
