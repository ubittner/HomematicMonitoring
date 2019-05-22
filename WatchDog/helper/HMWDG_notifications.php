<?php

// Declare
declare(strict_types=1);

trait HMWDG_notifications
{

    //#################### Send notification

    /**
     * Sends a push or an e-mail notification.
     *
     * @param int  $VariableID
     * @param bool $Overdue
     */
    protected function SendNotification(int $VariableID, bool $Overdue)
    {
        if (!$this->ReadPropertyBoolean('UseNotification') || !$this->GetValue('Monitoring')) {
            return;
        }
        IPS_LogMessage('Noti1', 'yes');
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
                if ($messageText->Status == $Overdue) {
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
        // Create text
        if (!empty($locationDesignation)) {
            $text = $locationDesignation . ', ' . $variableName . ', ' . $statusText . "\n\n" . $timeStamp . ', ID: ' . $VariableID . ', ' . $variableName . ', ' . $this->Translate('Address') . ': ' . $address . ', ' . $statusText;
        } else {
            $text = $variableName . ', ' . $statusText . "\n\n" . $timeStamp . ', ID: ' . $VariableID . ', ' . $variableName . ', ' . $this->Translate('Address') . ': ' . $address . ', ' . $statusText;
        }
        IPS_LogMessage('SendNotification', $title . ' ' . $text);
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
     * @param bool $Overdue
     */
    protected function UpdateLastMessage(int $VariableID, bool $Overdue)
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
                if ($messageText->Status == $Overdue) {
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
            $text = $timeStamp . ', ' . $locationDesignation . ', ID: ' . $VariableID . ', ' . $variableName . ', ' . $this->Translate('Address') . ': ' . $address . ', ' . $statusText;
        } else {
            $text = $timeStamp . ', ID: ' . $VariableID . ', ' . $variableName . ', ' . $this->Translate('Address') . ': ' . $address . ', ' . $statusText;
        }
        $this->SetValue('LastMessage', $text);
    }
}
