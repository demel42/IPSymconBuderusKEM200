<?php

declare(strict_types=1);

if (!defined('VARIABLETYPE_BOOLEAN')) {
    define('VARIABLETYPE_BOOLEAN', 0);
    define('VARIABLETYPE_INTEGER', 1);
    define('VARIABLETYPE_FLOAT', 2);
    define('VARIABLETYPE_STRING', 3);
}

if (!defined('OBJECTTYPE_CATEGORY')) {
    define('OBJECTTYPE_CATEGORY', 0);
    define('OBJECTTYPE_INSTANCE', 1);
    define('OBJECTTYPE_VARIABLE', 2);
    define('OBJECTTYPE_SCRIPT', 3);
    define('OBJECTTYPE_EVENT', 4);
    define('OBJECTTYPE_MEDIA', 5);
    define('OBJECTTYPE_LINK', 6);
}

if (!defined('MEDIATYPE_DOCUMENT')) {
    define('MEDIATYPE_DOCUMENT', 5);
}

if (!defined('IS_INVALIDCONFIG')) {
    define('IS_INVALIDCONFIG', IS_EBASE + 1);
}

trait BuderusKM200Common
{
    protected function SetValue($Ident, $Value)
    {
        @$varID = $this->GetIDForIdent($Ident);
        if ($varID == false) {
            $this->SendDebug(__FUNCTION__, 'missing variable ' . $Ident, 0);
            return;
        }

        @$ret = parent::SetValue($Ident, $Value);
        if ($ret == false) {
            $this->SendDebug(__FUNCTION__, 'mismatch of value "' . $Value . '" for variable ' . $Ident, 0);
        }
    }

    protected function GetValue($Ident)
    {
        @$varID = $this->GetIDForIdent($Ident);
        if ($varID == false) {
            $this->SendDebug(__FUNCTION__, 'missing variable ' . $Ident, 0);
            return false;
        }

        $ret = parent::GetValue($Ident);
        return $ret;
    }

    private function CreateVarProfile($Name, $ProfileType, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits, $Icon, $Associations = '')
    {
        if (!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, $ProfileType);
            IPS_SetVariableProfileText($Name, '', $Suffix);
            if (in_array($ProfileType, [VARIABLETYPE_INTEGER, VARIABLETYPE_FLOAT])) {
                IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
                IPS_SetVariableProfileDigits($Name, $Digits);
            }
            IPS_SetVariableProfileIcon($Name, $Icon);
            if ($Associations != '') {
                foreach ($Associations as $a) {
                    $w = isset($a['Wert']) ? $a['Wert'] : '';
                    $n = isset($a['Name']) ? $a['Name'] : '';
                    $i = isset($a['Icon']) ? $a['Icon'] : '';
                    $f = isset($a['Farbe']) ? $a['Farbe'] : 0;
                    IPS_SetVariableProfileAssociation($Name, $w, $n, $i, $f);
                }
            }
        }
    }

    // Inspired from module SymconTest/HookServe
    private function RegisterHook($WebHook)
    {
        $ids = IPS_GetInstanceListByModuleID('{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}');
        if (count($ids) > 0) {
            $hooks = json_decode(IPS_GetProperty($ids[0], 'Hooks'), true);
            $found = false;
            foreach ($hooks as $index => $hook) {
                if ($hook['Hook'] == $WebHook) {
                    if ($hook['TargetID'] == $this->InstanceID) {
                        return;
                    }
                    $hooks[$index]['TargetID'] = $this->InstanceID;
                    $found = true;
                }
            }
            if (!$found) {
                $hooks[] = ['Hook' => $WebHook, 'TargetID' => $this->InstanceID];
            }
            IPS_SetProperty($ids[0], 'Hooks', json_encode($hooks));
            IPS_ApplyChanges($ids[0]);
        }
    }

    // Inspired from module SymconTest/HookServe
    private function GetMimeType($extension)
    {
        $lines = file(IPS_GetKernelDirEx() . 'mime.types');
        foreach ($lines as $line) {
            $type = explode("\t", $line, 2);
            if (count($type) == 2) {
                $types = explode(' ', trim($type[1]));
                foreach ($types as $ext) {
                    if ($ext == $extension) {
                        return $type[0];
                    }
                }
            }
        }
        return 'text/plain';
    }

    private function GetArrayElem($data, $var, $dflt)
    {
        $ret = $data;
        $vs = explode('.', $var);
        foreach ($vs as $v) {
            if (!isset($ret[$v])) {
                $ret = $dflt;
                break;
            }
            $ret = $ret[$v];
        }
        return $ret;
    }

    // inspired by Nall-chan
    //   https://github.com/Nall-chan/IPSSqueezeBox/blob/6bbdccc23a0de51bb3fbc114cefc3acf23c27a14/libs/SqueezeBoxTraits.php
    public function __get($name)
    {
        $n = strpos($name, 'Multi_');
        if (strpos($name, 'Multi_') === 0) {
            $curCount = $this->GetBuffer('BufferCount_' . $name);
            if ($curCount == false) {
                $curCount = 0;
            }
            $data = '';
            for ($i = 0; $i < $curCount; $i++) {
                $data .= $this->GetBuffer('BufferPart' . $i . '_' . $name);
            }
        } else {
            $data = $this->GetBuffer($name);
        }
        return unserialize($data);
    }

    public function __set($name, $value)
    {
        $data = serialize($value);
        $n = strpos($name, 'Multi_');
        if (strpos($name, 'Multi_') === 0) {
            $oldCount = $this->GetBuffer('BufferCount_' . $name);
            if ($oldCount == false) {
                $oldCount = 0;
            }
            $parts = str_split($data, 8000);
            $newCount = count($parts);
            $this->SetBuffer('BufferCount_' . $name, $newCount);
            for ($i = 0; $i < $newCount; $i++) {
                $this->SetBuffer('BufferPart' . $i . '_' . $name, $parts[$i]);
            }
            for ($i = $newCount; $i < $oldCount; $i++) {
                $this->SetBuffer('BufferPart' . $i . '_' . $name, '');
            }
        } else {
            $this->SetBuffer($name, $data);
        }
    }

    private function SetMultiBuffer($name, $value)
    {
        $this->{'Multi_' . $name} = $value;
    }

    private function GetMultiBuffer($name)
    {
        $value = $this->{'Multi_' . $name};
        return $value;
    }

    protected function GetStatus()
    {
        if (IPS_GetKernelVersion() >= 5.1) {
            return parent::GetStatus();
        }

        $inst = IPS_GetInstance($this->InstanceID);
        return $inst['InstanceStatus'];
    }
}
