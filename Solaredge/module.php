<?php

declare(strict_types=1);

require_once __DIR__.'/../libs/myFunctions.php';  // globale Funktionen

define("DEVELOPMENT", false);

// Modul Prefix
if (!defined('MODUL_PREFIX')) {
    define("MODUL_PREFIX", "Solaredge");
    define("MODUL_ID", "{865418A8-B4D1-B05C-D1A4-FCF2873A15D6}");
}

class Solaredge extends IPSModule
{
    use myFunctions;
    public function __construct($InstanceID)
    {
        //Never delete this line!
        parent::__construct($InstanceID);
    }

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->ConnectParent(MODBUS_INSTANCES);

        $this->RegisterPropertyInteger("Poller", 0);
        $this->RegisterPropertyInteger("Phase", 1);

        $this->RegisterTimer("Poller", 0, "CGEM24_RequestRead(\$_IPS['TARGET']);");
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $this->RegisterVariableFloat("Volt", "Volt", "Volt.230", 1);
        $this->RegisterVariableFloat("Ampere", "Ampere", "Ampere.16", 2);
        $this->RegisterVariableFloat("Watt", "Watt", "Watt.14490", 3);
        $this->RegisterVariableFloat("kWh", "Total kWh", "Electricity", 4);

        $this->SetTimerInterval("Poller", $this->ReadPropertyInteger("Poller"));
    }

    public function RequestRead()
    {
        $Address = 0x00 + ($this->ReadPropertyInteger("Phase") - 1)*2;
        $Volt = $this->SendDataToParent(json_encode(array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => $Address , "Quantity" => 2, "Data" => "")));
        if ($Volt === false) {
            return;
        }
        $Volt = (unpack("n*", substr($Volt, 2)));

        $Address = 0x0C + ($this->ReadPropertyInteger("Phase") - 1)*2;
        $Ampere = $this->SendDataToParent(json_encode(array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => $Address , "Quantity" => 2, "Data" => "")));
        if ($Ampere === false) {
            return;
        }
        $Ampere = (unpack("n*", substr($Ampere, 2)));

        $Address = 0x12 + ($this->ReadPropertyInteger("Phase") - 1)*2;
        $Watt = $this->SendDataToParent(json_encode(array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => $Address , "Quantity" => 2, "Data" => "")));
        if ($Watt === false) {
            return;
        }
        $Watt = (unpack("n*", substr($Watt, 2)));

        $Address = 0x46 + ($this->ReadPropertyInteger("Phase") - 1)*2;
        $KWh = $this->SendDataToParent(json_encode(array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => $Address , "Quantity" => 2, "Data" => "")));
        if ($KWh === false) {
            return;
        }
        $KWh = (unpack("n*", substr($KWh, 2)));

        if (IPS_GetProperty(IPS_GetInstance($this->InstanceID)['ConnectionID'], "SwapWords")) {
            SetValue($this->GetIDForIdent("Volt"), ($Volt[1] + ($Volt[2] << 16))/10);
            SetValue($this->GetIDForIdent("Ampere"), ($Ampere[1] + ($Ampere[2] << 16))/1000);
            SetValue($this->GetIDForIdent("Watt"), ($Watt[1] + ($Watt[2] << 16))/10);
            SetValue($this->GetIDForIdent("kWh"), ($KWh[1] + ($KWh[2] << 16))/10);
        } else {
            SetValue($this->GetIDForIdent("Volt"), ($Volt[2] + ($Volt[1] << 16))/10);
            SetValue($this->GetIDForIdent("Ampere"), ($Ampere[2] + ($Ampere[1] << 16))/1000);
            SetValue($this->GetIDForIdent("Watt"), ($Watt[2] + ($Watt[1] << 16))/10);
            SetValue($this->GetIDForIdent("kWh"), ($KWh[2] + ($KWh[1] << 16))/10);
        }
    }
}
