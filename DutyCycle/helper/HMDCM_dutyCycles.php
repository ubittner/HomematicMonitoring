<?php

// Declare
declare(strict_types=1);

trait HMDCM_dutyCycles
{
    //#################### Updates

    /**
     * Sets the update timer to the next update interval.
     */
    protected function SetUpdateTimer()
    {
        $milliseconds = 0;
        // Check if monitoring is still active
        if ($this->ReadPropertyBoolean('UseUpdateInterval')) {
            // Set timer to next interval
            $milliseconds = $this->ReadPropertyInteger('UpdateInterval') * 60000;
        }
        $this->SetTimerInterval('Update', $milliseconds);
    }

    /**
     *  Updates the variables of the CCU DutyCycles.
     */
    public function UpdateDutyCycle()
    {
        $results = $this->GetData();
        if (!empty($results)) {
            foreach ($results as $result) {
                foreach ($result as $data) {
                    $variable = @$this->GetIDForIdent('DutyCycle' . $data->ADDRESS);
                    $value = $data->DUTY_CYCLE;
                    if ($variable && IPS_ObjectExists($variable)) {
                        SetValue($variable, $value);
                    }
                }
            }
        }
        $this->SetUpdateTimer();
    }

    /**
     * Gets the data form the Homematic CCU.
     *
     * @return array
     */
    protected function GetData(): array
    {
        $results = [];
        if (!$this->HasActiveParent()) {
            return [];
        }
        // Get parent
        $parentID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($parentID != 0 && IPS_ObjectExists($parentID)) {
            // Check status
            $status = IPS_GetInstance($parentID)['InstanceStatus'];
            if ($status == 102) {
                // Get activated Homematic protocols
                $protocols = array();
                if (IPS_GetProperty($parentID, 'RFOpen') === true) {
                    $protocols[] = 0;
                }
                if (IPS_GetProperty($parentID, 'IPOpen') === true) {
                    $protocols[] = 2;
                }
                $data = array();
                $parentData = array(
                    'DataID' => '{75B6B237-A7B0-46B9-BBCE-8DF0CFE6FA52}',
                    'Protocol' => 0,
                    'MethodName' => 'listBidcosInterfaces',
                    'WaitTime' => 5000,
                    'Data' => $data
                );
                foreach ($protocols as $protocol) {
                    $parentData['Protocol'] = $protocol;
                    $dataEncoded = json_encode($parentData);
                    $resultData = $this->SendDataToParent($dataEncoded);
                    array_push($results, json_decode($resultData));
                }
            }
        }
        $this->SendDebug('CCU Data', json_encode($results), 0);
        return $results;
    }

    //#################### Register message

    /**
     * Registers the variables for MessageSink.
     * If a virtual channel is used for an update of the duty cycle, it will be registered.
     * All activated variables from the monitored variables list will be registered.
     */
    protected function RegisterMonitoredVariables()
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
        // Virtual channel
        $virtualChannel = $this->ReadPropertyInteger('VirtualChannel');
        if ($virtualChannel != 0 && IPS_ObjectExists($virtualChannel)) {
            $this->RegisterMessage($virtualChannel, VM_UPDATE);
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
     * Determines the duty cycle variables automatically.
     */
    public function DetermineVariables()
    {
        $variables = [];
        // Get data
        $results = $this->GetData();
        if (!empty($results)) {
            // Variable profile
            $profileName = 'HMDCM.' . $this->InstanceID . '.DutyCycle';
            $i = 2;
            $j = 1;
            foreach ($results as $result) {
                foreach ($result as $data) {
                    $address = $data->ADDRESS;
                    if (@!$this->GetIDForIdent('DutyCycle' . $address)) {
                        $this->RegisterVariableInteger('DutyCycle' . $address, $data->TYPE . ' ' . $address, $profileName, $i);
                    }
                    $this->SetValue('DutyCycle' . $address, $data->DUTY_CYCLE);
                    array_push($variables, array(
                            'Position' => $j,
                            'ID' => $this->GetIDForIdent('DutyCycle' . $address),
                            'Name' => $data->TYPE,
                            'Address' => $address,
                            'UseMonitoring' => true)
                    );
                    $i++;
                    $j++;
                }
            }
            // Write data to configuration form
            $json = json_encode($variables);
            IPS_SetProperty($this->InstanceID, 'MonitoredVariables', $json);
            if (IPS_HasChanges($this->InstanceID)) {
                IPS_ApplyChanges($this->InstanceID);
            }
        }
        echo $this->Translate('Variables were determined and assigned automatically!');
    }

    //#################### Check variables

    /**
     * Checks the monitored variables.
     * If a variable is not used anymore, it will be deleted.
     * If a variable has changes, it will be changed to the actual values.
     * Not existing variables will be created.
     */
    protected function CheckMonitoredVariables()
    {
        // Get existing variables
        $variables = [];
        $children = IPS_GetChildrenIDs($this->InstanceID);
        foreach ($children as $child) {
            $object = IPS_GetObject($child);
            // Check if child is a varaible
            if ($object['ObjectType'] == 2) {
                $ident = $object['ObjectIdent'];
                if ($ident !== 'Monitoring' && $ident !== 'Status' && $ident !== 'LastMessage') {
                    $name = IPS_GetName($child);
                    array_push($variables, array('ID' => $child, 'Name' => $name, 'UseMonitoring' => true));
                }
            }
        }
        // Get already listed variables
        $listedVariables = json_decode($this->ReadPropertyString('MonitoredVariables'), true);
        if (!empty($listedVariables)) {
            // Delete variables
            $deleteVariables = array_diff(array_column($variables, 'ID'), array_column($listedVariables, 'ID'));
            if (!empty($deleteVariables)) {
                foreach ($deleteVariables as $key => $deleteVariable) {
                    IPS_DeleteVariable($deleteVariable);
                }
            }
            $overhead = 1;
            foreach ($listedVariables as $key => $listedVariable) {
                $variableID = $listedVariable['ID'];
                // Set position
                if ($variableID != 0 && IPS_ObjectExists($variableID)) {
                    $position = $listedVariable['Position'];
                    IPS_SetPosition($variableID, $position + $overhead);
                    // Rename variables
                    $variableName = $listedVariable['Name'];
                    IPS_SetName($variableID, $variableName);
                    // Hide variables which should not be monitored
                    $monitored = $listedVariable['UseMonitoring'];
                    $hiddenMode = false;
                    if (!$monitored) {
                        $hiddenMode = true;
                    }
                    IPS_SetHidden($variableID, $hiddenMode);
                }
            }
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
        $thresholdValue = $this->ReadPropertyInteger('ThresholdValue');
        $monitoredVariables = json_decode($this->ReadPropertyString('MonitoredVariables'));
        if (!empty($monitoredVariables)) {
            foreach ($monitoredVariables as $variable) {
                if ($variable->UseMonitoring) {
                    $value = (integer)GetValue($variable->ID);
                    if ($value >= $thresholdValue) {
                        $actualStatus = true;
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

    //#################### Create variable links

    /**
     * Creates links of the monitored variables which are activated.
     */
    public function CreateVariableLinks()
    {
        $categoryID = $this->ReadPropertyInteger('LinkCategory');
        // Define icon first
        $icon = 'Information';
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
                if ($linkInfo == 'HMDCM.' . $this->InstanceID) {
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
                IPS_SetInfo($linkID, 'HMDCM.' . $this->InstanceID);
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
                IPS_SetInfo($linkID, 'HMDCM.' . $this->InstanceID);
                IPS_SetIcon($linkID, $icon);
            }
        }
        echo $this->Translate('Links were successfully created!');
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
            $value = (integer)abs(GetValue($VariableID));
            $thresholdValue = (integer)abs($this->ReadPropertyInteger('ThresholdValue'));
            if ($value < $thresholdValue) {
                $notification = $this->CheckNotificationBelowThreshold($VariableID);
            }
            if ($value >= $thresholdValue) {
                $notification = $this->CheckNotificationThresholdReached($VariableID);
                $threshold = true;
            }
            // Notification
            if ($notification) {
                $this->UpdateLastMessage($VariableID, $threshold);
                $this->SendNotification($VariableID, $threshold);
            }
            // Check if status has changed and execute alerting
            $this->CheckStatusChange($threshold);
        }
    }

    //#################### Check status

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
}