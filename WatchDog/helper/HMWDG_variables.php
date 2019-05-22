<?php

// Declare
declare(strict_types=1);

trait HMWDG_variables
{
    /**
     * Gets the whitelist.
     * The whitelist is used for variables with an actual status update.
     *
     * @return array
     */
    public function GetWhitelist(): array
    {
        return json_decode($this->ReadAttributeString('Whitelist'), true);
    }

    /**
     * Gets the blacklist.
     * The blacklist is used for variables with a status update overdue.
     *
     * @return array
     */
    public function GetBlacklist(): array
    {
        return json_decode($this->ReadAttributeString('Blacklist'), true);
    }

    //#################### Determine variables

    /**
     * Determines the variables with the ident STATE automatically.
     */
    public function DetermineVariables()
    {
        $listedVariables = [];
        $instanceIDs = IPS_GetInstanceListByModuleID(HOMEMATIC_MODULE_GUID);
        if (!empty($instanceIDs)) {
            $variables = [];
            foreach ($instanceIDs as $instanceID) {
                $childrenIDs = IPS_GetChildrenIDs($instanceID);
                foreach ($childrenIDs as $childrenID) {
                    $match = false;
                    $object = IPS_GetObject($childrenID);
                    if ($object['ObjectIdent'] == 'STATE') {
                        $match = true;
                    }
                    if ($match) {
                        // Check for variable
                        if ($object['ObjectType'] == 2) {
                            $name = strstr(IPS_GetName($instanceID), ':', true);
                            if ($name == false) {
                                $name = IPS_GetName($instanceID);
                            }
                            $deviceAddress = @IPS_GetProperty(IPS_GetParent($childrenID), 'Address');
                            $lastMaintenance = '{"year":0,"month":0,"day":0}';
                            array_push($variables, ['ID' => $childrenID, 'Name' => $name, 'Address' => $deviceAddress, 'UseMonitoring' => true, 'LastMaintenance' => $lastMaintenance]);
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
                        $deviceAddress = @IPS_GetProperty(IPS_GetParent($addVariable), 'Address');
                        $lastMaintenance = '{"year":0,"month":0,"day":0}';
                        array_push($listedVariables, ['ID' => $addVariable, 'Name' => $name, 'Address' => $deviceAddress, 'UseMonitoring' => true, 'LastMaintenance' => $lastMaintenance]);
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

    //#################### Reindex variables

    public function ReindexVariablePosition()
    {
        $monitoredVariables = json_decode($this->ReadPropertyString('MonitoredVariables'));
        if (!empty($monitoredVariables)) {
            $variables = [];
            $i = 1;
            foreach ($monitoredVariables as $monitoredVariable) {
                $variables[] = ['Position' => $i, 'ID' => $monitoredVariable->ID, 'Name' => $monitoredVariable->Name, 'Address' => $monitoredVariable->Address, 'UseMonitoring' => $monitoredVariable->UseMonitoring, 'LastMaintenance' => $monitoredVariable->LastMaintenance];
                $i++;
            }
            // Update variable list
            $json = json_encode($variables);
            IPS_SetProperty($this->InstanceID, 'MonitoredVariables', $json);
            if (IPS_HasChanges($this->InstanceID)) {
                IPS_ApplyChanges($this->InstanceID);
            }
            echo $this->Translate('The position was successfully re-indexed!');
        }
    }

    //#################### Get assigned variables

    /**
     * Get the assigned variables.
     *
     * @return array

    public function GetAssignedVariables(): array
    {
        $variables = json_decode($this->ReadPropertyString('MonitoredVariables'));
        return $variables;
    }
     * */

    //#################### Delete variables

    /**
     * Deletes all assigned variables.
     */
    public function DeleteAssignedVariables()
    {
        $variables = [];
        $json = json_encode($variables);
        IPS_SetProperty($this->InstanceID, 'MonitoredVariables', $json);
        if (IPS_HasChanges($this->InstanceID)) {
            IPS_ApplyChanges($this->InstanceID);
        }
        echo $this->Translate('All assigned variables were deleted!');

    }

    //#################### Check monitored variables

    /**
     * Checks the monitored variables.
     */
    public function CheckVariables()
    {
        $status = $this->GetValue('Status');
        $alertVariables = $this->GetAlertVariables();
        $actualStatus = true;
        if (empty($alertVariables)) {
            $actualStatus = false;
        }
        $this->SetValue('Status', $actualStatus);
        $this->SetValue('LastCheck', time());
        $this->UpdateAlertView($alertVariables);
        if ($actualStatus != $status) {
            // Execute Alerting
            $this->ExecuteAlerting($actualStatus);
        }
    }

    /**
     * Gets the variables which have an overdue state.
     *
     * @return array
     */
    public function GetAlertVariables(): array
    {
        $variableIDs = array_column($this->GetMonitoredVariables(), 'ID');
        $watchTime = $this->GetWatchTime();
        $watchTimeBorder = time() - $watchTime;
        $alertVariables = [];
        foreach ($variableIDs as $variableID) {
            $variable = IPS_GetVariable($variableID);
            if ($variable['VariableUpdated'] < $watchTimeBorder) {
                $alertVariables[] = ['VariableID' => $variableID, 'LastUpdate' => $variable['VariableUpdated']];
                //$notification = $this->CheckNotificationThresholdReached($variableID);
                $threshold = true;
            } else {
                //$notification = $this->CheckNotificationBelowThreshold($variableID);
                $threshold = false;
            }
            /*
            if ($notification && $this->GetValue('Monitoring')) {
                $this->UpdateLastMessage($variableID, $threshold);
                $this->SendNotification($variableID, $threshold);
            }
            */
        }
        return $alertVariables;
    }

    /**
     * Gets the ids of all monitored variables.
     *
     * @return array
     */
    public function GetMonitoredVariables(): array
    {
        $monitoredVariables = [];
        $variables = json_decode($this->ReadPropertyString('MonitoredVariables'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                $variableID = $variable->ID;
                if ($variableID != 0 && IPS_ObjectExists($variableID) && $variable->UseMonitoring) {
                    $object = IPS_GetObject($variableID);
                    // Check for variable
                    if ($object['ObjectType'] == 2) {
                        // Check ident
                        if ($object['ObjectIdent'] == 'STATE') {
                            $monitoredVariables[] = ['Position' => $variable->Position, 'ID' => $variableID, 'Name' => $variable->Name, 'Address' => $variable->Address, 'UseMonitoring' => $variable->UseMonitoring, 'LastMaintenance' => $variable->LastMaintenance];
                            //$monitoredVariables[] = $variableID;
                        }
                    }
                }
            }
        }
        return $monitoredVariables;
    }

    /**
     * Gets the watch time.
     *
     * @return float|int
     */
    private function GetWatchTime()
    {
        $time = 0;
        $timeBase = $this->ReadPropertyInteger("TimeBase");
        $timeValue = $this->ReadPropertyInteger("TimeValue");
        switch($timeBase) {
            case 0:
                $time = $timeValue;
                break;
            case 1:
                $time = $timeValue * 60;
                break;

            case 2:
                $time = $timeValue * 3600;
                break;

            case 3:
                $time = $timeValue * 86400;
                break;
        }
        return $time;
    }

    /**
     * Updates the alert view.
     *
     * @param $AlertVariables
     */
    private function UpdateAlertView($AlertVariables)
    {
        // Header
        $html = "<table style='width: 100%; border-collapse: collapse;'>";
        $html .= "<tr>";
        $html .= "<td style='padding: 5px; font-weight: bold;'>Sensor</td>";
        $html .= "<td style='padding: 5px; font-weight: bold;'>Letzte Aktualisierung</td>";
        $html .= "<td style='padding: 5px; font-weight: bold;'>Überfällig seit</td>";
        $html .= "</tr>";
        // Content
        $monitoredVariables = $this->GetMonitoredVariables();
        foreach ($AlertVariables as $alertVariable) {
            $id = array_column($monitoredVariables, 'ID');
            $key = array_search($alertVariable['VariableID'], $id);
            $name = $monitoredVariables[$key]['Name'];
            $timediff = time() - $alertVariable['LastUpdate'];
            $timestring = sprintf("%02d:%02d:%02d", (int)($timediff / 3600), (int)($timediff / 60) % 60, ($timediff) % 60);
            $html .= "<tr style='border-top: 1px solid rgba(255,255,255,0.10);'>";
            $html .= "<td style='padding: 5px;'>" . $name . "</td>";
            $html .= "<td style='padding: 5px;'>" . date("d.m.Y H:i:s", $alertVariable['LastUpdate']) . "</td>";
            $html .= "<td style='padding: 5px;'>" . $timestring . " Stunden</td>";
            $html .= "</tr>";
        }
        $html .= "</table>";
        SetValue($this->GetIDForIdent("AlertView"), $html);
    }
}