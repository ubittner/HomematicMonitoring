<?php

// Declare
declare(strict_types=1);

trait HMSCM_alerting
{
    /**
     * If monitoring is activated, a variable will be switched and/or a script will be executed.
     *
     * @param bool $State
     */
    protected function ExecuteAlerting(bool $State)
    {
        // Check monitoring
        if ($this->GetIDForIdent('Monitoring')) {
            // Variables
            $variables = json_decode($this->ReadPropertyString('TargetVariables'));
            if (!empty($variables)) {
                foreach ($variables as $variable) {
                    if ($variable->ID != 0 && IPS_ObjectExists($variable->ID)) {
                        $object = IPS_GetObject($variable->ID);
                        if ($object['ObjectType'] == 2) {
                            if ($variable->UseVariable) {
                                RequestAction($variable->ID, $State);
                            }
                        }
                    }
                }
            }
            // Scripts
            $scripts = json_decode($this->ReadPropertyString('TargetScripts'));
            if (!empty($scripts)) {
                foreach ($scripts as $script) {
                    if ($script->ID != 0 && IPS_ObjectExists($script->ID)) {
                        $object = IPS_GetObject($script->ID);
                        if ($object['ObjectType'] == 3) {
                            if ($script->UseScript) {
                                IPS_RunScriptEx($script->ID, array('Status' => $State));
                            }
                        }
                    }
                }
            }
        }
    }
}