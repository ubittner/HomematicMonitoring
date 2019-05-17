<?php

// Declare
declare(strict_types=1);

trait HMSCM_notifications
{

    //#################### Send notification

    /**
     * Sends a push or an e-mail notification.
     *
     * @param bool $State
     */
    protected function SendNotification(bool $State)
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
        $messageTexts = json_decode($this->ReadPropertyString('MessageTexts'));
        if (!empty($messageTexts)) {
            foreach ($messageTexts as $messageText) {
                if ($messageText->Status == $State) {
                    $statusText = $messageText->MessageText;
                    $sound = $messageText->Sound;
                }
            }
        }
        // Create text
        if (!empty($locationDesignation)) {
            $text = $locationDesignation . ', ' . $statusText . "\n\n" . $timeStamp . ', ' . $statusText;
        } else {
            $text = $statusText . "\n\n" . $timeStamp . ', ' . $statusText;
        }
        // Push notification
        $webFronts = json_decode($this->ReadPropertyString('WebFronts'));
        if (!empty($webFronts)) {
            foreach ($webFronts as $webFront) {
                if ($webFront->UseNotification) {
                    $moduleID = IPS_GetInstance($webFront->ID)['ModuleInfo']['ModuleID'];
                    if ($webFront->ID != 0 && IPS_ObjectExists($webFront->ID) && $moduleID == WEBFRONT_GUID) {
                        WFC_PushNotification($webFront->ID, $title, "\n" . $text, (string)$sound, 0);
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
     * @param bool $State
     */
    protected function UpdateLastMessage(bool $State)
    {
        $timeStamp = date('d.m.Y, H:i:s');
        // Get location designation
        $locationDesignation = $this->ReadPropertyString('LocationDesignation');
        // Get status text
        $statusText = '';
        $messageTexts = json_decode($this->ReadPropertyString('MessageTexts'));
        if (!empty($messageTexts)) {
            foreach ($messageTexts as $messageText) {
                if ($messageText->Status == $State) {
                    $statusText = $messageText->MessageText;
                }
            }
        }
        // Create text
        if (!empty($locationDesignation)) {
            $text = $timeStamp . ', ' . $locationDesignation . ', ' . $statusText;
        } else {
            $text = $timeStamp . ', ' . $statusText;
        }
        $this->SetValue('LastMessage', $text);
    }

    /**
     * Shows the notification cycles.
     */
    public function ShowNotificationCycles()
    {
        echo $this->Translate('Connection lost: ') . "\n" . $this->ReadAttributeInteger('ConnectionLostNotificationCycle') . "\n";
        echo $this->Translate('Connection established: ') . "\n" . $this->ReadAttributeInteger('ConnectionEstablishedNotificationCycle') . "\n";
    }
}