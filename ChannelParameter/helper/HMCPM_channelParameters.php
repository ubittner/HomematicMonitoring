<?php

// Declare
declare(strict_types=1);

trait HMCPM_channelParameters
{
    //#################### Register message

    /**
     * Registers the variables for MessageSink.
     * All activated variables from the monitored variables list will be registered.
     */
    private function RegisterMonitoredVariables()
    {
        // Delete registered variables first
        $registeredVariables = $this->GetMessageList();
        foreach ($registeredVariables as $id => $registeredVariable) {
            foreach ($registeredVariable as $messageType) {
                if ($messageType == VM_UPDATE) {
                    $this->UnregisterMessage($id, VM_UPDATE);
                }
            }
        }
        // Register variables to be monitored
        $variables = json_decode($this->ReadPropertyString("MonitoredVariables"));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                if ($variable->ID != 0 && IPS_ObjectExists($variable->ID) && $variable->UseMonitoring) {
                    $this->RegisterMessage($variable->ID, VM_UPDATE);
                }
            }
        }
    }

    /**
     * Displays the registered variables for MessageSink.
     *
     * @return array
     */
    public function DisplayRegisteredVariables(): array
    {
        $monitoredVariables = [];
        $registeredVariables = $this->GetMessageList();
        foreach ($registeredVariables as $id => $registeredVariable) {
            foreach ($registeredVariable as $messageType) {
                if ($messageType == VM_UPDATE) {
                    array_push($monitoredVariables, $id);
                }
            }
        }
        return $monitoredVariables;
    }

    //#################### Buffer

    /**
     * Displays the variables which are below the defined threshold value.
     *
     * @return array
     */
    public function DisplayVariablesBelowThreshold(): array
    {
        $variables = json_decode($this->GetBuffer('VariablesBelowThreshold'));
        return $variables;
    }

    /**
     * Displays the blocked variables below the defined threshold value for today.
     *
     * @return array
     */
    public function DisplayBlockedVariablesForTodayBelowThreshold(): array
    {
        $blockedVariables = json_decode($this->GetBuffer('BlockedVariablesForTodayBelowThreshold'));
        return $blockedVariables;
    }

    /**
     * Displays the variables which have reached or exceeded the defined threshold value.
     *
     * @return array
     */
    public function DisplayVariablesThresholdReached(): array
    {
        $variables = json_decode($this->GetBuffer('VariablesThresholdReached'));
        return $variables;
    }

    /**
     * Displays the blocked variables which have reached or exceeded the defined threshold value for today.
     *
     * @return array
     */
    public function DisplayBlockedVariablesForTodayThresholdReached(): array
    {
        $blockedVariables = json_decode($this->GetBuffer('BlockedVariablesForTodayThresholdReached'));
        return $blockedVariables;
    }

    //#################### Determine variables

    /**
     * Determines the variables automatically.
     */
    public function DetermineVariables()
    {
        $listedVariables = [];
        $instanceIDs = IPS_GetInstanceListByModuleID(HOMEMATIC_MODULE_GUID);
        $parameter = $this->ReadPropertyString('Parameter');
        if (!empty($instanceIDs)) {
            $variables = [];
            foreach ($instanceIDs as $instanceID) {
                $childrenIDs = IPS_GetChildrenIDs($instanceID);
                foreach ($childrenIDs as $childrenID) {
                    $match = false;
                    $object = IPS_GetObject($childrenID);
                    switch ($parameter) {
                        case 'DUTY_CYCLE':
                            if ($object['ObjectIdent'] == 'DUTY_CYCLE') {
                                $match = true;
                            }
                            break;
                        case 'LOWBAT/LOW_BAT':
                            if ($object['ObjectIdent'] == 'LOWBAT' || $object['ObjectIdent'] == 'LOW_BAT') {
                                $match = true;
                            }
                            break;
                        case 'RSSI_DEVICE':
                            if ($object['ObjectIdent'] == 'RSSI_DEVICE') {
                                $match = true;
                            }
                            break;
                        case 'RSSI_PEER':
                            if ($object['ObjectIdent'] == 'RSSI_PEER') {
                                $match = true;
                            }
                            break;
                        case 'SABOTAGE/ERROR_SABOTAGE':
                            if ($object['ObjectIdent'] == 'SABOTAGE' || $object['ObjectIdent'] == 'ERROR_SABOTAGE') {
                                $match = true;
                            }
                            break;
                        case 'UNREACH/STICKY_UNREACH':
                            if ($object['ObjectIdent'] == 'UNREACH' || $object['ObjectIdent'] == 'STICKY_UNREACH') {
                                $match = true;
                            }
                            break;
                        case 'USER_DEFINED':
                            $userDefinedParameters = $this->ReadPropertyString('UserDefinedParameters');
                            if (!empty($userDefinedParameters)) {
                                $userDefinedParameters = str_replace(' ', '', $userDefinedParameters);
                                $userDefinedParameters = explode(',', $userDefinedParameters);
                                foreach ($userDefinedParameters as $userDefinedParameter) {
                                    if ($object['ObjectIdent'] == $userDefinedParameter) {
                                        $match = true;
                                    }
                                }
                            }
                            break;
                        default:
                            $match = false;
                    }
                    if ($match) {
                        // Check for variable
                        if ($object['ObjectType'] == 2) {
                            $name = strstr(IPS_GetName($instanceID), ':', true);
                            $deviceAddress = @IPS_GetProperty(IPS_GetParent($childrenID), 'Address');
                            array_push($variables, array('ID' => $childrenID, 'Name' => $name, 'Address' => $deviceAddress, 'UseMonitoring' => true));
                        }
                    }
                }
            }
            // Get already listed variables
            $listedVariables = json_decode($this->ReadPropertyString('MonitoredVariables'), true);
            // Delete non existing variables anymore
            if (!empty($listedVariables)) {
                $deleteVariables = array_diff(array_column($listedVariables, 'ID'), array_column($variables, 'ID'));
                if (!empty($deleteVariables)) {
                    foreach ($deleteVariables as $key => $deleteVariable) {
                        unset($listedVariables[$key]);
                    }
                }
            }
            // Add new variables
            if (!empty($listedVariables)) {
                $addVariables = array_diff(array_column($variables, 'ID'), array_column($listedVariables, 'ID'));
                if (!empty($addVariables)) {
                    foreach ($addVariables as $addVariable) {
                        $name = strstr(IPS_GetName(IPS_GetParent($addVariable)), ':', true);
                        array_push($listedVariables, array('ID' => $addVariable, 'Name' => $name, 'UseMonitoring' => true));
                    }
                }
            } else {
                $listedVariables = $variables;
            }
        }
        // Sort variables by name
        usort($listedVariables, function ($a, $b) {
            return $a['Name'] <=> $b['Name'];
        });
        // Rebase array
        $listedVariables = array_values($listedVariables);
        // Rebase position
        foreach ($listedVariables as $key => $existingSabotageSensor) {
            $listedVariables[$key]['Position'] = $key + 1;
        }
        // Update variable list
        $json = json_encode($listedVariables);
        IPS_SetProperty($this->InstanceID, 'MonitoredVariables', $json);
        if (IPS_HasChanges($this->InstanceID)) {
            IPS_ApplyChanges($this->InstanceID);
        }
        echo $this->Translate('Variables were determined and assigned automatically!');
    }

    //################### Assign variable profile

    /**
     * Assigns the profile to the variable.
     *
     * @param bool $Override
     *
     * If $Override is false, only variables with no existing profiles will be assigned.
     * If $Override is true, existing profiles will be overwritten.
     *
     */
    public function AssignVariableProfile(bool $Override)
    {
        $parameter = $this->ReadPropertyString('Parameter');
        // Create profiles
        switch ($parameter) {
            case 'LOWBAT/LOW_BAT':
                // Variable type for Homematic is boolean
                $profile = 'HMCPM.Battery';
                if (!IPS_VariableProfileExists($profile)) {
                    IPS_CreateVariableProfile($profile, 0);
                }
                IPS_SetVariableProfileAssociation($profile, 0, 'OK', 'Battery', 0x00FF00);
                IPS_SetVariableProfileAssociation($profile, 1, $this->Translate('Low battery'), 'Battery', 0xFF0000);
                break;
            case 'SABOTAGE/ERROR_SABOTAGE':
                // Variable type for Homematic is integer
                $profile = 'HMCPM.HM.Sabotage';
                if (!IPS_VariableProfileExists($profile)) {
                    IPS_CreateVariableProfile($profile, 1);
                }
                IPS_SetVariableProfileAssociation($profile, 0, 'OK', 'Information', 0x00FF00);
                IPS_SetVariableProfileAssociation($profile, 1, 'Sabotage', 'Warning', 0xFF0000);
                // Variable type for Homematic IP is boolean
                $profile = 'HMCPM.HMIP.Sabotage';
                if (!IPS_VariableProfileExists($profile)) {
                    IPS_CreateVariableProfile($profile, 0);
                }
                IPS_SetVariableProfileAssociation($profile, 0, 'OK', 'Information', 0x00FF00);
                IPS_SetVariableProfileAssociation($profile, 1, 'Sabotage', 'Warning', 0xFF0000);
                break;
        }
        // Assign profile only for listed variables
        $variables = json_decode($this->ReadPropertyString('MonitoredVariables'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                $variableType = IPS_GetVariable($variable->ID)['VariableType'];
                $profileName = null;
                switch ($parameter) {
                    case 'LOWBAT/LOW_BAT':
                        // Boolean variable
                        if ($variableType == 0) {
                            $profileName = 'HMCPM.Battery';
                        }
                        break;
                    case 'SABOTAGE/ERROR_SABOTAGE':
                        // Boolean variable
                        if ($variableType == 0) {
                            $profileName = 'HMCPM.HMIP.Sabotage';
                        }
                        // Integer variable
                        if ($variableType == 1) {
                            $profileName = 'HMCPM.HM.Sabotage';
                        }
                        break;

                }
                // Always assign profile
                if ($Override) {
                    if (!is_null($profileName)) {
                        IPS_SetVariableCustomProfile($variable->ID, $profileName);
                    }
                } // Only assign profile, if variable has no profile
                else {
                    // Check if variable has a profile
                    $assignedProfile = IPS_GetVariable($variable->ID)['VariableProfile'];
                    if (empty($assignedProfile)) {
                        IPS_SetVariableCustomProfile($variable->ID, $profileName);
                    }
                }
            }
        }
        echo $this->Translate('Variable profiles were assigned automatically!');
    }

    //#################### Create overview

    /**
     * Creates an overview of monitored variables for WebFront.
     */
    private function CreateOverview()
    {
        $useDisplayOverview = $this->ReadPropertyBoolean('UseOverview');
        if ($useDisplayOverview && $this->GetIDForIdent('Overview')) {
            $string = "<table width='90%' align='center'>";
            $string .= $this->Translate("<tr><td><b>ID</b></td><td><b>Name</b></td><td><b>Address</b></td><td><b>State</b></td></tr>");
            $variables = json_decode($this->ReadPropertyString('MonitoredVariables'));
            if (!empty($variables)) {
                foreach ($variables as $variable) {
                    $actualStateName = '';
                    $profile = IPS_GetVariable($variable->ID)['VariableCustomProfile'];
                    if (!empty($profile)) {
                        $actualStateName = GetValueFormatted($variable->ID);
                    }
                    $devices = json_decode($this->GetBuffer('VariablesThresholdReached'), true);
                    $thresholdReached = in_array($variable->ID, $devices);
                    if ($thresholdReached) {
                        $text = '<span style="color:#FF0000"><b>' . $actualStateName . '</b></span>';
                    } else {
                        $text = $actualStateName;
                    }
                    $deviceAddress = @IPS_GetProperty(IPS_GetParent($variable->ID), 'Address');
                    if (!$deviceAddress) {
                        $deviceAddress = '-';
                    }
                    $string .= "<tr><td>" . $variable->ID . "</td><td>" . $variable->Name . "</td><td>" . $deviceAddress . "</td><td>" . $text . "</td></tr>";
                }
                $string .= "</table>";
                $this->SetValue('Overview', $string);
            }
        }
    }

    //#################### Create variable links

    /**
     * Creates links of monitored variables.
     */
    public function CreateVariableLinks()
    {
        $categoryID = $this->ReadPropertyInteger('LinkCategory');
        // Define icon first
        $parameter = $this->ReadPropertyString('Parameter');
        switch ($parameter) {
            case 'LOWBAT/LOW_BAT':
                $icon = 'Battery';
                break;
            default:
                $icon = 'Information';
        }
        // Get all monitored variables
        $variables = json_decode($this->ReadPropertyString('MonitoredVariables'));
        $targetIDs = [];
        $i = 0;
        foreach ($variables as $variable) {
            if ($variable->UseMonitoring) {
                $targetIDs[$i] = ['name' => $variable->Name, 'targetID' => $variable->ID];
                $i++;
            }
        }
        // Sort array alphabetically by device name
        sort($targetIDs);
        // Get all existing links (links have not an ident field, so we use the object info field)
        $existingTargetIDs = [];
        $links = IPS_GetLinkList();
        if (!empty($links)) {
            $i = 0;
            foreach ($links as $link) {
                $linkInfo = IPS_GetObject($link)['ObjectInfo'];
                if ($linkInfo == 'HMCPM.' . $this->InstanceID) {
                    // Get target id
                    $existingTargetID = IPS_GetLink($link)['TargetID'];
                    $existingTargetIDs[$i] = ['linkID' => $link, 'targetID' => $existingTargetID];
                    $i++;
                }
            }
        }
        // Delete dead links
        $deadLinks = array_diff(array_column($existingTargetIDs, 'targetID'), array_column($targetIDs, 'targetID'));
        if (!empty($deadLinks)) {
            foreach ($deadLinks as $targetID) {
                $position = array_search($targetID, array_column($existingTargetIDs, 'targetID'));
                $linkID = $existingTargetIDs[$position]['linkID'];
                if (IPS_LinkExists($linkID)) {
                    IPS_DeleteLink($linkID);
                }
            }
        }
        // Create new links
        $newLinks = array_diff(array_column($targetIDs, 'targetID'), array_column($existingTargetIDs, 'targetID'));
        if (!empty($newLinks)) {
            foreach ($newLinks as $targetID) {
                $linkID = IPS_CreateLink();
                IPS_SetParent($linkID, $categoryID);
                $position = array_search($targetID, array_column($targetIDs, 'targetID'));
                IPS_SetPosition($linkID, $position + 1);
                $name = $targetIDs[$position]['name'];
                IPS_SetName($linkID, $name);
                IPS_SetLinkTargetID($linkID, $targetID);
                IPS_SetInfo($linkID, 'HMCPM.' . $this->InstanceID);
                IPS_SetIcon($linkID, $icon);
            }
        }
        // Edit existing links
        $existingLinks = array_intersect(array_column($existingTargetIDs, 'targetID'), array_column($targetIDs, 'targetID'));
        if (!empty($existingLinks)) {
            foreach ($existingLinks as $targetID) {
                $position = array_search($targetID, array_column($targetIDs, 'targetID'));
                $targetID = $targetIDs[$position]['targetID'];
                $index = array_search($targetID, array_column($existingTargetIDs, 'targetID'));
                $linkID = $existingTargetIDs[$index]['linkID'];
                IPS_SetPosition($linkID, $position + 3);
                $name = $targetIDs[$position]['name'];
                IPS_SetName($linkID, $name);
                IPS_SetInfo($linkID, 'HMCPM.' . $this->InstanceID);
                IPS_SetIcon($linkID, $icon);
            }
        }
        echo $this->Translate('Links were successfully created!');
    }

    //#################### Reset message limit

    /**
     * Sets the reset limit timer to next day.
     */
    private function SetResetMessageLimitTimer()
    {
        $timestamp = strtotime('next day midnight');
        $now = time();
        $interval = ($timestamp - $now) * 1000;
        $this->SetTimerInterval('ResetMessageLimit', $interval);
    }

    /**
     * Resets the daily message limit.
     */
    public function ResetMessageLimit()
    {
        // Reset limit
        $this->SetBuffer('BlockedVariablesForTodayBelowThreshold', json_encode([]));
        $this->SetBuffer('BlockedVariablesForTodayThresholdReached', json_encode([]));
        $this->SetResetMessageLimitTimer();
    }

    //#################### Trigger alert

    /**
     * Triggers an alert.
     *
     * @param int $VariableID
     */
    private function TriggerAlert(int $VariableID)
    {
        if ($this->GetValue('Monitoring')) {
            $notification = false;
            $threshold = false;
            $parameter = $this->ReadPropertyString('Parameter');
            switch ($parameter) {
                case 'DUTY_CYCLE':
                case 'LOWBAT/LOW_BAT':
                case 'SABOTAGE/ERROR_SABOTAGE':
                case 'UNREACH/STICKY_UNREACH':
                    $value = (bool)GetValue($VariableID);
                    // Value is false
                    if (!$value) {
                        $notification = $this->CheckNotificationBelowThreshold($VariableID);
                    }
                    // Value is true
                    if ($value) {
                        $notification = $this->CheckNotificationThresholdReached($VariableID);
                        $threshold = true;
                    }
                    break;
                case 'RSSI_DEVICE':
                case 'RSSI_PEER':
                    $thresholdValue = (integer)abs($this->ReadPropertyString('ThresholdValue'));
                    $value = (integer)abs(GetValue($VariableID));
                    // Value below threshold
                    if ($value < $thresholdValue) {
                        $notification = $this->CheckNotificationBelowThreshold($VariableID);
                    }
                    // Value reached threshold
                    if ($value >= $thresholdValue) {
                        $notification = $this->CheckNotificationThresholdReached($VariableID);
                        $threshold = true;
                    }
                    break;
                case 'USER_DEFINED':
                    // Get variable type first
                    $variableType = IPS_GetVariable($VariableID)['VariableType'];
                    switch ($variableType) {
                        case 0:
                            // Boolean
                            $value = (bool)GetValue($VariableID);
                            $thresholdValue = (bool)$this->ReadPropertyString('ThresholdValue');
                            if ($value != $thresholdValue) {
                                $notification = $this->CheckNotificationBelowThreshold($VariableID);
                            }
                            if ($value == $thresholdValue) {
                                $notification = $this->CheckNotificationThresholdReached($VariableID);
                                $threshold = true;
                            }
                            break;
                        case 1:
                            // Integer
                            $value = (integer)abs(GetValue($VariableID));
                            $thresholdValue = (integer)abs($this->ReadPropertyString('ThresholdValue'));
                            if ($value < $thresholdValue) {
                                $notification = $this->CheckNotificationBelowThreshold($VariableID);
                            }
                            if ($value >= $thresholdValue) {
                                $notification = $this->CheckNotificationThresholdReached($VariableID);
                                $threshold = true;
                            }
                            break;
                        case 2:
                            // Float
                            $value = (float)abs(GetValue($VariableID));
                            $thresholdValue = (float)abs($this->ReadPropertyString('ThresholdValue'));
                            if ($value < $thresholdValue) {
                                $notification = $this->CheckNotificationBelowThreshold($VariableID);
                            }
                            if ($value >= $thresholdValue) {
                                $notification = $this->CheckNotificationThresholdReached($VariableID);
                                $threshold = true;
                            }
                            break;
                        case 3:
                            // String
                            $value = (string)GetValue($VariableID);
                            $thresholdValue = (string)$this->ReadPropertyString('ThresholdValue');
                            if ($value != $thresholdValue) {
                                $notification = $this->CheckNotificationBelowThreshold($VariableID);
                            }
                            if ($value == $thresholdValue) {
                                $notification = $this->CheckNotificationThresholdReached($VariableID);
                                $threshold = true;
                            }
                            break;
                    }
                    break;
            }
            // Notification
            if ($notification) {
                $this->UpdateLastMessage($VariableID, $threshold);
                $this->SendNotification($VariableID, $threshold);
            }
            // Check if status has changed and execute alerting
            $this->CheckStatusChange($threshold);
            // Create overview
            $this->CreateOverview();
        }
    }

    /**
     * Checks if the general status has changed.
     *
     * @param bool $State
     */
    private function CheckStatusChange(bool $State)
    {
        if (!$State) {
            $status = false;
            $variables = json_decode($this->GetBuffer('VariablesThresholdReached'), true);
            if (!empty($variables)) {
                $status = true;
            }
        } else {
            $status = true;
        }
        $execute = false;
        $variableStatus = GetValueBoolean($this->GetIDForIdent('Status'));
        if ($variableStatus != $status) {
            $this->SetValue('Status', $status);
            $execute = true;
        }
        if ($execute) {
            // Execute Alerting
            $this->ExecuteAlerting($State);
        }
    }

    /**
     * Checks the actual status.
     * Used in ApplyChanges() to get the actual status and
     * if the status has changed it toggles the activated alerting variables and/or
     * executes the activated alerting scripts.
     * Only activated variables for monitoring will be used.
     */
    protected function CheckActualStatus()
    {
        $actualStatus = false;
        $parameter = $this->ReadPropertyString('Parameter');
        $monitoredVariables = json_decode($this->ReadPropertyString('MonitoredVariables'));
        if (!empty($monitoredVariables)) {
            foreach ($monitoredVariables as $variable) {
                if ($variable->UseMonitoring) {
                    switch ($parameter) {
                        case 'DUTY_CYCLE':
                        case 'LOWBAT/LOW_BAT':
                        case 'SABOTAGE/ERROR_SABOTAGE':
                        case 'UNREACH/STICKY_UNREACH':
                            $value = (bool)GetValue($variable->ID);
                            if ($value) {
                                $actualStatus = true;
                            }
                            break;
                        case 'RSSI_DEVICE':
                        case 'RSSI_PEER':
                            $thresholdValue = (integer)abs($this->ReadPropertyString('ThresholdValue'));
                            $value = (integer)abs(GetValue($variable->ID));
                            if ($value >= $thresholdValue) {
                                $actualStatus = true;
                            }
                            break;
                        case 'USER_DEFINED':
                            // Get variable type first
                            $variableType = IPS_GetVariable($variable->ID)['VariableType'];
                            switch ($variableType) {
                                case 0:
                                    // Boolean
                                    $value = (bool)GetValue($variable->ID);
                                    $thresholdValue = (bool)$this->ReadPropertyString('ThresholdValue');
                                    if ($value == $thresholdValue) {
                                        $actualStatus = true;
                                    }
                                    break;
                                case 1:
                                    // Integer
                                    $value = (integer)abs(GetValue($variable->ID));
                                    $thresholdValue = (integer)abs($this->ReadPropertyString('ThresholdValue'));
                                    if ($value >= $thresholdValue) {
                                        $actualStatus = true;
                                    }
                                    break;
                                case 2:
                                    // Float
                                    $value = (float)abs(GetValue($variable->ID));
                                    $thresholdValue = (float)abs($this->ReadPropertyString('ThresholdValue'));
                                    if ($value >= $thresholdValue) {
                                        $actualStatus = true;
                                    }
                                    break;
                                case 3:
                                    // String
                                    $value = (string)GetValue($variable->ID);
                                    $thresholdValue = (string)$this->ReadPropertyString('ThresholdValue');
                                    if ($value == $thresholdValue) {
                                        $actualStatus = true;
                                    }
                                    break;
                            }
                            break;
                    }
                }
            }
        }
        $status = $this->GetValue('Status');
        $this->SetValue('Status', $actualStatus);
        if ($actualStatus != $status) {
            // Execute Alerting
            $this->ExecuteAlerting($actualStatus);
        }
    }
}