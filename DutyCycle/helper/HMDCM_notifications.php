<?php

// Declare
declare(strict_types=1);

trait HMDCM_notifications
{
    //#################### Check notification

    /**
     * Checks the notification of a variable, if the actual value is below the threshold value.
     *
     * @param int $VariableID
     *
     * @return bool
     */
    protected function CheckNotificationBelowThreshold(int $VariableID): bool
    {
        $notification = false;
        if (empty($VariableID)) {
            return $notification;
        }
        if ($VariableID != 0 && IPS_ObjectExists($VariableID)) {
            $useMessageDayLimit = $this->ReadPropertyBoolean('UseMessageDayLimitBelowThreshold');
            $useNotifyOnce = $this->ReadPropertyBoolean('NotifyOnceBelowThreshold');
            $useAlwaysNotify = $this->ReadPropertyBoolean('AlwaysNotifyBelowThreshold');
            $blockedVariablesForToday = json_decode($this->GetBuffer('BlockedVariablesForTodayBelowThreshold'), true);
            $blockedForToday = in_array($VariableID, $blockedVariablesForToday);
            $variablesBelowThreshold = json_decode($this->GetBuffer('VariablesBelowThreshold'), true);
            $belowThreshold = in_array($VariableID, $variablesBelowThreshold);
            // Check limitation of messages per day
            if ($useMessageDayLimit) {
                // Not blocked for today
                if (!$blockedForToday) {
                    // Block variable for today
                    array_push($blockedVariablesForToday, $VariableID);
                    $this->SetBuffer('BlockedVariablesForTodayBelowThreshold', json_encode($blockedVariablesForToday));
                    // Check notify once
                    if ($useNotifyOnce) {
                        if (!$belowThreshold) {
                            $notification = true;
                            array_push($variablesBelowThreshold, $VariableID);
                        }
                    }
                    // Check always notify
                    if ($useAlwaysNotify) {
                        $notification = true;
                    }
                    // No notification is used
                    if (!$useNotifyOnce && !$useAlwaysNotify) {
                        array_push($variablesBelowThreshold, $VariableID);
                    }
                }
            } // No limitation of messages per day
            else {
                // Check notify once
                if ($useNotifyOnce) {
                    if (!$belowThreshold) {
                        $notification = true;
                        array_push($variablesBelowThreshold, $VariableID);
                    }
                }
                // Check always notify
                if ($useAlwaysNotify) {
                    $notification = true;
                }
                // No notification is used
                if (!$useNotifyOnce && !$useAlwaysNotify) {
                    array_push($variablesBelowThreshold, $VariableID);
                }
            }
            // Set variables below threshold buffer
            $variablesBelowThreshold = array_unique($variablesBelowThreshold);
            $this->SetBuffer('VariablesBelowThreshold', json_encode($variablesBelowThreshold));
            // Remove variable from threshold reached buffer
            $variablesThresholdReached = json_decode($this->GetBuffer('VariablesThresholdReached'), true);
            if (in_array($VariableID, $variablesThresholdReached)) {
                unset($variablesThresholdReached[array_search($VariableID, $variablesThresholdReached)]);
                // Rebase
                $variablesThresholdReached = array_values($variablesThresholdReached);
                $this->SetBuffer('VariablesThresholdReached', json_encode($variablesThresholdReached));
            }
        }
        return $notification;
    }

    /**
     * Checks the notification of a variable, if the actual value has reached the threshold value or is exceeded.
     *
     * @param int $VariableID
     *
     * @return bool
     */
    protected function CheckNotificationThresholdReached(int $VariableID): bool
    {
        $notification = false;
        if (empty($VariableID)) {
            return $notification;
        }
        if ($VariableID != 0 && IPS_ObjectExists($VariableID)) {
            $useMessageDayLimit = $this->ReadPropertyBoolean('UseMessageDayLimitThresholdReached');
            $useNotifyOnce = $this->ReadPropertyBoolean('NotifyOnceThresholdReached');
            $useAlwaysNotify = $this->ReadPropertyBoolean('AlwaysNotifyThresholdReached');
            $blockedVariablesForToday = json_decode($this->GetBuffer('BlockedVariablesForTodayThresholdReached'), true);
            $blockedForToday = in_array($VariableID, $blockedVariablesForToday);
            $variablesThresholdReached = json_decode($this->GetBuffer('VariablesThresholdReached'), true);
            $thresholdReached = in_array($VariableID, $variablesThresholdReached);
            // Check limitation of messages per day
            if ($useMessageDayLimit) {
                // Not blocked for today
                if (!$blockedForToday) {
                    // Block variable for today
                    array_push($blockedVariablesForToday, $VariableID);
                    $this->SetBuffer('BlockedVariablesForTodayThresholdReached', json_encode($blockedVariablesForToday));
                    // Check notify once
                    if ($useNotifyOnce) {
                        if (!$thresholdReached) {
                            $notification = true;
                            array_push($variablesThresholdReached, $VariableID);
                        }
                    }
                    // Check always notify
                    if ($useAlwaysNotify) {
                        $notification = true;
                    }
                    // No notification is used
                    if (!$useNotifyOnce && !$useAlwaysNotify) {
                        array_push($variablesThresholdReached, $VariableID);
                    }
                }
            } // No limitation of messages per day
            else {
                // Check notify once
                if ($useNotifyOnce) {
                    if (!$thresholdReached) {
                        $notification = true;
                        array_push($variablesThresholdReached, $VariableID);
                    }
                }
                // Check always notify
                if ($useAlwaysNotify) {
                    $notification = true;
                }
                // No notification is used
                if (!$useNotifyOnce && !$useAlwaysNotify) {
                    array_push($variablesThresholdReached, $VariableID);
                }
            }
            // Set variables threshold reached buffer
            $variablesThresholdReached = array_unique($variablesThresholdReached);
            $this->SetBuffer('VariablesThresholdReached', json_encode($variablesThresholdReached));
            // Remove variable from below threshold buffer
            $variablesBelowThreshold = json_decode($this->GetBuffer('VariablesBelowThreshold'), true);
            if (in_array($VariableID, $variablesBelowThreshold)) {
                unset($variablesBelowThreshold[array_search($VariableID, $variablesBelowThreshold)]);
                // Rebase
                $variablesBelowThreshold = array_values($variablesBelowThreshold);
                $this->SetBuffer('VariablesBelowThreshold', json_encode($variablesBelowThreshold));
            }
        }
        return $notification;
    }

    //#################### Send notification

    /**
     * Sends a push or an e-mail notification.
     *
     * @param int  $VariableID
     * @param bool $Threshold
     */
    protected function SendNotification(int $VariableID, bool $Threshold)
    {
        if (!$this->ReadPropertyBoolean('UseNotification')) {
            return;
        }
        $timeStamp = date('d.m.Y, H:i:s');
        // Get title
        $title = substr($this->ReadPropertyString('TitleDescription'), 0, 32);
        // Get location designation
        $locationDesignation = $this->ReadPropertyString('LocationDesignation');
        // Get status text
        $statusText = '';
        $sound = '';
        $address = '-';
        $messageTexts = json_decode($this->ReadPropertyString('MessageTexts'));
        if (!empty($messageTexts)) {
            foreach ($messageTexts as $messageText) {
                if ($messageText->Status == $Threshold) {
                    $statusText = $messageText->MessageText;
                    $sound = $messageText->Sound;
                }
            }
        }
        // Get variable name and address
        $variableName = IPS_GetName($VariableID);
        $monitoredVariables = json_decode($this->ReadPropertyString('MonitoredVariables'));
        if (!empty($monitoredVariables)) {
            foreach ($monitoredVariables as $monitoredVariable) {
                if ($monitoredVariable->ID == $VariableID) {
                    $variableName = $monitoredVariable->Name;
                    $address = $monitoredVariable->Address;
                }
            }
        }
        $value = GetValueFormatted($VariableID);
        // Create text
        if (!empty($locationDesignation)) {
            $text = $locationDesignation . ', ' . $variableName . ', ' . $value . "\n\n" . $timeStamp . ', ' . $variableName . ', ' . $this->Translate('Address') . ': ' . $address . ', ' . $statusText;
        } else {
            $text = $variableName . ', ' . $value . "\n\n" . $timeStamp . ', ' . $variableName . ', ' . $this->Translate('Address') . ': ' . $address . ', ' . $statusText;
        }
        // Push notification
        $webFronts = json_decode($this->ReadPropertyString('WebFronts'));
        if (!empty($webFronts)) {
            foreach ($webFronts as $webFront) {
                if ($webFront->UseNotification) {
                    $moduleID = IPS_GetInstance($webFront->ID)['ModuleInfo']['ModuleID'];
                    if ($webFront->ID != 0 && IPS_ObjectExists($webFront->ID) && $moduleID == WEBFRONT_GUID) {
                        WFC_PushNotification($webFront->ID, $title, "\n" . $text, (string) $sound, 0);
                    }
                }
            }
        }
        // E-mail notification
        $recipients = json_decode($this->ReadPropertyString('EmailRecipients'));
        if (!empty($recipients)) {
            foreach ($recipients as $recipient) {
                if ($recipient->UseNotification) {
                    $moduleID = IPS_GetInstance($recipient->ID)['ModuleInfo']['ModuleID'];
                    if ($recipient->ID != 0 && IPS_ObjectExists($recipient->ID) && $moduleID == MAIL_GUID) {
                        SMTP_SendMailEx($recipient->ID, $recipient->Address, $title, $text);
                    }
                }
            }
        }
    }

    /**
     * Updates the last message.
     *
     * @param int  $VariableID
     * @param bool $Threshold
     */
    protected function UpdateLastMessage(int $VariableID, bool $Threshold)
    {
        $timeStamp = date('d.m.Y, H:i:s');
        // Get location designation
        $locationDesignation = $this->ReadPropertyString('LocationDesignation');
        // Get status text
        $statusText = '';
        $address = '-';
        $messageTexts = json_decode($this->ReadPropertyString('MessageTexts'));
        if (!empty($messageTexts)) {
            foreach ($messageTexts as $messageText) {
                if ($messageText->Status == $Threshold) {
                    $statusText = $messageText->MessageText;
                }
            }
        }
        // Get variable name and address
        $variableName = IPS_GetName($VariableID);
        $monitoredVariables = json_decode($this->ReadPropertyString('MonitoredVariables'));
        if (!empty($monitoredVariables)) {
            foreach ($monitoredVariables as $monitoredVariable) {
                if ($monitoredVariable->ID == $VariableID) {
                    $variableName = $monitoredVariable->Name;
                    $address = $monitoredVariable->Address;
                }
            }
        }
        // Create text
        if (!empty($locationDesignation)) {
            $text = $timeStamp . ', ' . $locationDesignation . ', ' . $variableName . ', ' . $this->Translate('Address') . ': ' . $address . ', ' . $statusText;
        } else {
            $text = $timeStamp . ', ' . $variableName . ', ' . $this->Translate('Address') . ': ' . $address . ', ' . $statusText;
        }
        $this->SetValue('LastMessage', $text);
    }
}
