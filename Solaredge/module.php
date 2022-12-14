<?php

declare(strict_types=1);

require_once __DIR__.'/../libs/myFunctions.php';  // globale Funktionen

define("DEVELOPMENT", false);

// Modul Prefix
if (!defined('MODUL_PREFIX')) {
    define("MODUL_PREFIX", "Solaredge");
    define("MODUL_ID", "{865418A8-B4D1-B05C-D1A4-FCF2873A15D6}");
}

// ArrayOffsets
if (!defined('IMR_START_REGISTER'))
{
	define("IMR_START_REGISTER", 0);
	define("IMR_SIZE", 1);
	define("IMR_RW", 2);
	define("IMR_FUNCTION_CODE", 3);
	define("IMR_NAME", 4);
	define("IMR_DESCRIPTION", 5);
	define("IMR_TYPE", 6);
	define("IMR_UNITS", 7);
	define("IMR_SF", 8);
}

// Offset von Register (erster Wert 1) zu Adresse (erster Wert 0) ist -1
if (!defined('MODBUS_REGISTER_TO_ADDRESS_OFFSET'))
{
	define("MODBUS_REGISTER_TO_ADDRESS_OFFSET", -1);
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

        $this->RegisterPropertyBoolean('active', 'true');
        $this->RegisterPropertyString('hostIp', '');
        $this->RegisterPropertyInteger('hostPort', '502');
        $this->RegisterPropertyInteger('hostmodbusDevice', '1');
        $this->RegisterPropertyInteger('pollCycle', '60');

    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function GetConfigurationForm()
		{
			$libraryJson = @IPS_GetLibrary(MODUL_ID);
			
			$headline = MODUL_PREFIX." Modul";
			if(isset($libraryJson['Version']))
			{
				$headline .= " v".$libraryJson['Version'];
			}

			if(isset($libraryJson['Date']) && 0 != $libraryJson['Date'])
			{
				$headline .= " (".$libraryJson['Date'].")";
			}

			$formElements = array();
			$formElements[] = array(
				'type' => "Label",
				'label' => $headline,
				'bold' => true,
			);
			$formElements[] = array(
				'type' => "Label",
				'label' => "Der SolarEdge Wechselrichter oder SmartMeter (Energiezähler) muss Modbus TCP unterstützen!",
			);
			$formElements[] = array(
				'type' => "Label",
				'label' => "Im Konfigurationsmenü des SolarEdge Wechselrichters muss unter dem Menüpunkt 'Modbus' die Datenausgabe über Modbus per 'TCP' und der Sunspec Model Type 'float' aktiviert werden.",
			);
			$formElements[] = array(
				'type' => "Label",
				'label' => " ",
			);
			$formElements[] = array(
				'type' => "CheckBox",
				'caption' => "Open",
				'name' => "active",
			);
			$formElements[] = array(
				'type' => "ValidationTextBox",
				'caption' => "IP",
				'name' => "hostIp",
				'validate' => "^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$",
			);
			$formElements[] = array(
				'type' => "NumberSpinner",
				'caption' => "Port (Standard: 502)",
				'name' => "hostPort",
				'digits' => 0,
				'minimum' => 1,
				'maximum' => 65535,
			);
			$formElements[] = array(
				'type' => "Label",
				'label' => " ",
			);
			$formElements[] = array(
				'type' => "Label",
				'label' => "In welchem Zeitintervall sollen die Modbus-Werte abgefragt werden (Empfehlung: 10 bis 60 Sekunden)?",
			);
			$formElements[] = array(
				'type' => "NumberSpinner",
				'caption' => "Abfrage-Intervall (in Sekunden)",
				'name' => "pollCycle",
				'minimum' => 1,
				'maximum' => 3600,
			);
			$formElements[] = array(
				'type' => "Label",
				'label' => "Achtung: Die Berechnung der Wirkarbeit (Wh/kWh) wird exakter, je kleiner der Abfarge-Intervall gewählt wird.\nABER: Je kleiner der Abfrage-Intervall, um so höher die Systemlast und auch die Archiv-Größe bei aktiviertem Logging!",
			);
			$formElements[] = array(
				'type' => "Label",
				'label' => " ",
			);

			$formActions = array();

			$formStatus = array();
			$formStatus[] = array(
				'code' => IS_IPPORTERROR,
				'icon' => "error",
				'caption' => "IP oder Port sind nicht erreichtbar",
			);
			$formStatus[] = array(
				'code' => IS_NOARCHIVE,
				'icon' => "error",
				'caption' => "Archiv nicht gefunden",
			);

			return json_encode(array('elements' => $formElements, 'actions' => $formActions, 'status' => $formStatus));
		}

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        //Properties
        $active = $this->ReadPropertyBoolean('active');
        $hostIp = $this->ReadPropertyString('hostIp');
        $hostPort = $this->ReadPropertyInteger('hostPort');
        $hostmodbusDevice = $this->ReadPropertyInteger('hostmodbusDevice');
        $hostSwapWords = 0;
        $pollCycle = $this->ReadPropertyInteger('pollCycle') * 1000;

        $archiveId = $this->getArchiveId();
        if (false === $archiveId)
        {
            // no archive found
            $this->SetStatus(201);
        }

        // Workaround für "InstanceInterface not available" Fehlermeldung beim Server-Start...
        if (KR_READY != IPS_GetKernelRunlevel())
        {
            // --> do nothing
        }
        elseif ("" == $hostIp)
        {
            // keine IP --> inaktiv
            $this->SetStatus(104);
        }
        // Instanzen nur mit Konfigurierter IP erstellen
        else
        {
            $this->checkProfiles();
            list($gatewayId_Old, $interfaceId_Old) = $this->readOldModbusGateway();
            list($gatewayId, $interfaceId) = $this->checkModbusGateway($hostIp, $hostPort, $hostmodbusDevice, $hostSwapWords);

            $parentId = $this->InstanceID;
            $categoryId = $parentId;
            $categoryName = "Cat";
            $categoryInfo = "Cat Info";
            $inverterModelRegister_array = array(
                array(40044, 8, "R", 3, "C_Version", "Spezifischer SolarEdge Wert", "String16", ""),
                array(40068, 1, "R", 3, "C_Geräteadresse", "Modbus-ID der entsprechenden Einheit", "uint16", ""),
                array(40071, 1, "R", 3, "I_AC_Strom", "AC-Gesamtstromwert", "uint16", "A"),
                array(40072, 1, "R", 3, "I_AC_StromA", "AC-Phase A (L1) Stromwert", "uint16", "A"),
                array(40073, 1, "R", 3, "I_AC_StromB", "AC-Phase B (L2) Stromwert", "uint16", "A"),
                array(40074, 1, "R", 3, "I_AC_StromC", "AC-Phase C (L3) Stromwert", "uint16", "A"),
                array(40075, 1, "R", 3, "I_AC_Strom_SF", "AC-Strom Skalierungsfaktor", "int16", ""),
                array(40079, 1, "R", 3, "I_AC_SpannungAN", "AC-Spannung Phase A-N (L1-N) Wert", "uint16", "V"),
                array(40080, 1, "R", 3, "I_AC_SpannungBN", "AC-Spannung Phase B-N (L2-N) Wert", "uint16", "V"),
                array(40081, 1, "R", 3, "I_AC_SpannungCN", "AC-Spannung Phase C-N (L3-N) Wert", "uint16", "V"),
                array(40082, 1, "R", 3, "I_AC_Spannung_SF", "AC-Spannung Skalierungsfaktor", "int16", ""),
                array(40083, 1, "R", 3, "I_AC_Leistung", "AC-Leistungswert", "int16", "W"),
                array(40084, 1, "R", 3, "I_AC_Leistung_SF", "AC-Leistung Skalierungsfaktor", "int16", ""),
                array(40085, 1, "R", 3, "I_AC_Frequenz", "Frequenzwert", "uint16", "Hz"),
                array(40086, 1, "R", 3, "I_AC_Frequenz_SF", "Frequenz Skalierungsfaktor", "int16", ""),
                array(40087, 1, "R", 3, "I_AC_VA", "Scheinleistung", "int16", "VA"),
                array(40088, 1, "R", 3, "I_AC_VA_SF", "Scheinleistung Skalierungsfaktor", "int16", ""),
                array(40089, 1, "R", 3, "I_AC_VAR", "Blindleistung", "int16", "VAR"),
                array(40090, 1, "R", 3, "I_AC_VAR_SF", "Blindleistung Skalierungsfaktor", "int16", ""),
                array(40091, 1, "R", 3, "I_AC_PF", "Leistungsfaktor", "int16", "%"),
                array(40092, 1, "R", 3, "I_AC_PF_SF", "Leistungsfaktor Skalierungsfaktor", "int16", ""),
                array(40093, 2, "R", 3, "I_AC_Energie_WH", "AC Gesamt-Energieproduktion", "acc32", "Wh"),
                array(40095, 1, "R", 3, "I_AC_Energie_WH_SF", "AC Gesamtenergie Skalierungsfaktor", "uint16", ""),
                array(40096, 1, "R", 3, "I_DC_Strom", "DC-Stromwert", "uint16", "A"),
                array(40097, 1, "R", 3, "I_DC_Strom_SF", "DC-Strom Skalierungsfaktor", "int16", ""),
                array(40098, 1, "R", 3, "I_DC_Spannung", "DC-Spannungswert", "uint16", "V"),
                array(40099, 1, "R", 3, "I_DC_Spannung_SF", "DC-Spannung Skalierungsfaktor", "int16", ""),
                array(40100, 1, "R", 3, "I_DC_Leistung", "DC-Leistungswert", "int16", "W"),
                array(40101, 1, "R", 3, "I_DC_Leistung_SF", "DC-Leistung Skalierungsfaktor", "int16", ""),
                array(40103, 1, "R", 3, "I_Temp_Kühler", "Kühlkörpertemperatur", "int16", "°C"),
                array(40106, 1, "R", 3, "I_Temp_SF", "Kühlkörpertemperatur Skalierungsfaktor", "int16", ""),
                array(40107, 1, "R", 3, "I_Status", "Betriebszustand (1 = Aus, 2 = Schlafen (Automatisches Herunterfahren) – Nachtmodus, 3 = Aufwachen/Starten, 4 = Wechselrichter ist AN und wandelt Energie, 5 = Begrenzte Produktion, 6 = Herunterfahren, 7 = Fehler, 8 = Wartung/Setup)", "uint16", ""),
                array(40108, 1, "R", 3, "I_Status_Anbieter", "Anbieter-spezifischer Betriebszustand sowie Fehlercodes: 1 = Aus, 2 = Schlafen (Automatisches Herunterfahren) – Nachtmodus, 3 = Aufwachen/Starten, 4 = Wechselrichter ist AN und wandelt Energie, 5 = Begrenzte Produktion, 6 = Herunterfahren, 7 = Fehler, 8 = Wartung/Setup", "uint16", ""),
                array(40206, 1, "R", 3, "M_AC_Power", "Total Real Power (sum of active phases)", "int16", "W"),
                array(40210, 1, "R", 3, "M_AC_Power_SF", "AC Real Power Scale Factor", "int16", ""),
                array(40226, 2, "R", 3, "M_Exported", "Total Exported Real Energy", "uint32", "Wh"),
                array(40242, 1, "R", 3, "M_Energy_W_SF", "Real Energy Scale Factor", "int16", "")
            );

            if (false === $categoryId)
            {
                $categoryId = IPS_CreateCategory();
                IPS_SetIdent($categoryId, $this->removeInvalidChars($categoryName));
                IPS_SetName($categoryId, $categoryName);
                IPS_SetParent($categoryId, $parentId);
                IPS_SetInfo($categoryId, $categoryInfo);
            }

            $this->createModbusInstances($inverterModelRegister_array, $categoryId, $gatewayId, $pollCycle);
        }

    }

    private function createModbusInstances($modelRegister_array, $parentId, $gatewayId, $pollCycle, $uniqueIdent = "")
    {
        // Workaround für "InstanceInterface not available" Fehlermeldung beim Server-Start...
        if (KR_READY == IPS_GetKernelRunlevel())
        {
            // Erstelle Modbus Instancen
            foreach ($modelRegister_array as $inverterModelRegister)
            {
                // get datatype
                $datenTyp = $this->getModbusDatatype($inverterModelRegister[IMR_TYPE]);
                if ("continue" == $datenTyp)
                {
                    continue;
                }

                /*					// if scale factor is given, variable will be of type float
                                    if (isset($inverterModelRegister[IMR_SF]) && 10000 >= $inverterModelRegister[IMR_SF])
                                    {
                                        $varDataType = MODBUSDATATYPE_REAL;
                                    }
                                    else
                    */					{
                    $varDataType = $datenTyp;
                }

                // get profile
                if (isset($inverterModelRegister[IMR_UNITS]))
                {
                    $profile = $this->getProfile($inverterModelRegister[IMR_UNITS], $varDataType);
                }
                else
                {
                    $profile = false;
                }

                $instanceId = @IPS_GetObjectIDByIdent($inverterModelRegister[IMR_START_REGISTER].$uniqueIdent, $parentId);
                $initialCreation = false;

                // Modbus-Instanz erstellen, sofern noch nicht vorhanden
                if (false === $instanceId)
                {
                    $this->SendDebug("create Modbus address", "REG_".$inverterModelRegister[IMR_START_REGISTER]." - ".$inverterModelRegister[IMR_NAME]." (modbusDataType=".$datenTyp.", varDataType=".$varDataType.", profile=".$profile.")", 0);

                    $instanceId = IPS_CreateInstance(MODBUS_ADDRESSES);

                    IPS_SetParent($instanceId, $parentId);
                    IPS_SetIdent($instanceId, $inverterModelRegister[IMR_START_REGISTER].$uniqueIdent);
                    IPS_SetName($instanceId, $inverterModelRegister[IMR_NAME]);
                    IPS_SetInfo($instanceId, $inverterModelRegister[IMR_DESCRIPTION]);

                    $initialCreation = true;
                }

                // Gateway setzen
                if (IPS_GetInstance($instanceId)['ConnectionID'] != $gatewayId)
                {
                    $this->SendDebug("set Modbus Gateway", "REG_".$inverterModelRegister[IMR_START_REGISTER]." - ".$inverterModelRegister[IMR_NAME]." --> GatewayID ".$gatewayId, 0);

                    // sofern bereits eine Gateway verbunden ist, dieses trennen
                    if (0 != IPS_GetInstance($instanceId)['ConnectionID'])
                    {
                        IPS_DisconnectInstance($instanceId);
                    }

                    // neues Gateway verbinden
                    IPS_ConnectInstance($instanceId, $gatewayId);
                }


                // ************************
                // config Modbus-Instance
                // ************************
                // set data type
                if ($datenTyp != IPS_GetProperty($instanceId, "DataType"))
                {
                    IPS_SetProperty($instanceId, "DataType", $datenTyp);
                }
                // set emulation state
                if (false != IPS_GetProperty($instanceId, "EmulateStatus"))
                {
                    IPS_SetProperty($instanceId, "EmulateStatus", false);
                }
                // set poll cycle
                if ($pollCycle != IPS_GetProperty($instanceId, "Poller"))
                {
                    IPS_SetProperty($instanceId, "Poller", $pollCycle);
                }
                // set length for modbus datatype string
                if (MODBUSDATATYPE_STRING == $datenTyp && $inverterModelRegister[IMR_SIZE] != IPS_GetProperty($instanceId, "Length"))
                { // if string --> set length accordingly
                    IPS_SetProperty($instanceId, "Length", $inverterModelRegister[IMR_SIZE]);
                }
                /*					// set scale factor
                                    if (isset($inverterModelRegister[IMR_SF]) && 10000 >= $inverterModelRegister[IMR_SF] && $inverterModelRegister[IMR_SF] != IPS_GetProperty($instanceId, "Factor"))
                                    {
                                        IPS_SetProperty($instanceId, "Factor", $inverterModelRegister[IMR_SF]);
                                    }
                    */

                // Read-Settings
                if ($inverterModelRegister[IMR_START_REGISTER] + MODBUS_REGISTER_TO_ADDRESS_OFFSET != IPS_GetProperty($instanceId, "ReadAddress"))
                {
                    IPS_SetProperty($instanceId, "ReadAddress", $inverterModelRegister[IMR_START_REGISTER] + MODBUS_REGISTER_TO_ADDRESS_OFFSET);
                }
                if (6 == $inverterModelRegister[IMR_FUNCTION_CODE])
                {
                    $ReadFunctionCode = 3;
                }
                elseif ("R" == $inverterModelRegister[IMR_FUNCTION_CODE])
                {
                    $ReadFunctionCode = 3;
                }
                elseif ("RW" == $inverterModelRegister[IMR_FUNCTION_CODE])
                {
                    $ReadFunctionCode = 3;
                }
                else
                {
                    $ReadFunctionCode = $inverterModelRegister[IMR_FUNCTION_CODE];
                }

                if ($ReadFunctionCode != IPS_GetProperty($instanceId, "ReadFunctionCode"))
                {
                    IPS_SetProperty($instanceId, "ReadFunctionCode", $ReadFunctionCode);
                }

                // Write-Settings
                if (4 < $inverterModelRegister[IMR_FUNCTION_CODE] && $inverterModelRegister[IMR_FUNCTION_CODE] != IPS_GetProperty($instanceId, "WriteFunctionCode"))
                {
                    IPS_SetProperty($instanceId, "WriteFunctionCode", $inverterModelRegister[IMR_FUNCTION_CODE]);
                }

                if (4 < $inverterModelRegister[IMR_FUNCTION_CODE] && $inverterModelRegister[IMR_START_REGISTER] + MODBUS_REGISTER_TO_ADDRESS_OFFSET != IPS_GetProperty($instanceId, "WriteAddress"))
                {
                    IPS_SetProperty($instanceId, "WriteAddress", $inverterModelRegister[IMR_START_REGISTER] + MODBUS_REGISTER_TO_ADDRESS_OFFSET);
                }

                if (0 != IPS_GetProperty($instanceId, "WriteFunctionCode"))
                {
                    IPS_SetProperty($instanceId, "WriteFunctionCode", 0);
                }

                if (IPS_HasChanges($instanceId))
                {
                    IPS_ApplyChanges($instanceId);
                }

                // Statusvariable der Modbus-Instanz ermitteln
                $varId = IPS_GetObjectIDByIdent("Value", $instanceId);

                // Profil der Statusvariable initial einmal zuweisen
                if (false != $profile && !IPS_VariableProfileExists($profile))
                {
                    $this->SendDebug("Variable-Profile", "Profile ".$profile." does not exist!", 0);
                }
                elseif ($initialCreation && false != $profile)
                {
                    // Justification Rule 11: es ist die Funktion RegisterVariable...() in diesem Fall nicht nutzbar, da die Variable durch die Modbus-Instanz bereits erstellt wurde
                    // --> Custo Profil wird initial einmal beim Instanz-erstellen gesetzt
                    if (!IPS_SetVariableCustomProfile($varId, $profile))
                    {
                        $this->SendDebug("Variable-Profile", "Error setting profile ".$profile." for VarID ".$varId."!", 0);
                    }
                }
            }
        }
    }
    
    private function getModbusDatatype(string $type)//PHP8 :mixed
    {
        // Datentyp ermitteln
        // 0=Bit (1 bit)
        // 1=Byte (8 bit unsigned)
        if ("uint8" == strtolower($type)
            || "enum8" == strtolower($type)
        ) {
            $datenTyp = MODBUSDATATYPE_BIT;
        }
        // 2=Word (16 bit unsigned)
        elseif ("uint16" == strtolower($type)
            || "enum16" == strtolower($type)
            || "uint8+uint8" == strtolower($type)
        ) {
            $datenTyp = MODBUSDATATYPE_WORD;
        }
        // 3=DWord (32 bit unsigned)
        elseif ("uint32" == strtolower($type)
            || "acc32" == strtolower($type)
            || "acc64" == strtolower($type)
        ) {
            $datenTyp = MODBUSDATATYPE_DWORD;
        }
        // 4=Char / ShortInt (8 bit signed)
        elseif ("sunssf" == strtolower($type)
            || "int8" == strtolower($type)
        ) {
            $datenTyp = MODBUSDATATYPE_CHAR;
        }
        // 5=Short / SmallInt (16 bit signed)
        elseif ("int16" == strtolower($type))
        {
            $datenTyp = MODBUSDATATYPE_SHORT;
        }
        // 6=Integer (32 bit signed)
        elseif ("int32" == strtolower($type))
        {
            $datenTyp = MODBUSDATATYPE_INT;
        }
        // 7=Real (32 bit signed)
        elseif ("float32" == strtolower($type))
        {
            $datenTyp = MODBUSDATATYPE_REAL;
        }
        // 8=Int64
        elseif ("uint64" == strtolower($type))
        {
            $datenTyp = MODBUSDATATYPE_INT64;
        }
        /* 9=Real64 (32 bit signed)
        elseif ("???" == strtolower($type))
        {
            $datenTyp = MODBUSDATATYPE_REAL64;
        }*/
        // 10=String
        elseif ("string32" == strtolower($type)
            || "string16" == strtolower($type)
            || "string8" == strtolower($type)
            || "string" == strtolower($type)
        ) {
            $datenTyp = MODBUSDATATYPE_STRING;
        }
        else
        {
            $this->SendDebug("getModbusDatatype()", "Unbekannter Datentyp '".$type."'! --> skip", 0);

            return "continue";
        }

        return $datenTyp;
    }

    private function getProfile(string $unit, int $datenTyp = -1)//PHP8 :mixed
    {
        // Profil ermitteln
        if ("a" == strtolower($unit) && MODBUSDATATYPE_REAL == $datenTyp)
        {
            $profile = "~Ampere";
        }
        elseif ("a" == strtolower($unit))
        {
            $profile = MODUL_PREFIX.".Ampere.Int";
        }
        elseif ("ma" == strtolower($unit))
        {
            $profile = MODUL_PREFIX.".MilliAmpere.Int";
        }
        elseif (("ah" == strtolower($unit)
                || "vah" == strtolower($unit))
            && MODBUSDATATYPE_REAL == $datenTyp
        ) {
            $profile = MODUL_PREFIX.".AmpereHour.Float";
        }
        elseif ("ah" == strtolower($unit)
            || "vah" == strtolower($unit)
        ) {
            $profile = MODUL_PREFIX.".AmpereHour.Int";
        }
        elseif ("v" == strtolower($unit) && MODBUSDATATYPE_REAL == $datenTyp)
        {
            $profile = "~Volt";
        }
        elseif ("v" == strtolower($unit))
        {
            $profile = MODUL_PREFIX.".Volt.Int";
        }
        elseif ("w" == strtolower($unit) && MODBUSDATATYPE_REAL == $datenTyp)
        {
            $profile = "~Watt.14490";
        }
        elseif ("w" == strtolower($unit))
        {
            $profile = MODUL_PREFIX.".Watt.Int";
        }
        elseif ("h" == strtolower($unit))
        {
            $profile = MODUL_PREFIX.".Hours.Int";
        }
        elseif ("hz" == strtolower($unit) && MODBUSDATATYPE_REAL == $datenTyp)
        {
            $profile = "~Hertz";
        }
        elseif ("hz" == strtolower($unit))
        {
            $profile = MODUL_PREFIX.".Hertz.Int";
        }
        elseif ("l/min" == strtolower($unit))
        {
            $profile = MODUL_PREFIX.".Volumenstrom.Int";
        }
        // Voltampere fuer elektrische Scheinleistung
        elseif ("va" == strtolower($unit) && MODBUSDATATYPE_REAL == $datenTyp)
        {
            $profile = MODUL_PREFIX.".Scheinleistung.Float";
        }
        // Voltampere fuer elektrische Scheinleistung
        elseif ("va" == strtolower($unit))
        {
            $profile = MODUL_PREFIX.".Scheinleistung.Int";
        }
        // Var fuer elektrische Blindleistung
        elseif ("var" == strtolower($unit) && MODBUSDATATYPE_REAL == $datenTyp)
        {
            $profile = MODUL_PREFIX.".Blindleistung.Float";
        }
        // Var fuer elektrische Blindleistung
        elseif ("var" == strtolower($unit) || "var" == $unit)
        {
            $profile = MODUL_PREFIX.".Blindleistung.Int";
        }
        elseif ("%" == $unit && MODBUSDATATYPE_REAL == $datenTyp)
        {
            $profile = "~Valve.F";
        }
        elseif ("%" == $unit)
        {
            $profile = "~Valve";
        }
        elseif ("wh" == strtolower($unit) && (MODBUSDATATYPE_REAL == $datenTyp || MODBUSDATATYPE_INT64 == $datenTyp))
        {
            $profile = MODUL_PREFIX.".Electricity.Float";
        }
        elseif ("wh" == strtolower($unit))
        {
            $profile = MODUL_PREFIX.".Electricity.Int";
        }
        elseif ((
            "° C" == $unit
                || "°C" == $unit
                || "C" == $unit
        ) && MODBUSDATATYPE_REAL == $datenTyp
        ) {
            $profile = "~Temperature";
        }
        elseif ("° C" == $unit
            || "°C" == $unit
            || "C" == $unit
        ) {
            $profile = MODUL_PREFIX.".Temperature.Int";
        }
        elseif ("cos()" == strtolower($unit) && MODBUSDATATYPE_REAL == $datenTyp)
        {
            $profile = MODUL_PREFIX.".Angle.Float";
        }
        elseif ("cos()" == strtolower($unit))
        {
            $profile = MODUL_PREFIX.".Angle.Int";
        }
        elseif ("ohm" == strtolower($unit))
        {
            $profile = MODUL_PREFIX.".Ohm.Int";
        }
        elseif ("enumerated_id" == strtolower($unit))
        {
            $profile = "SunSpec.ID.Int";
        }
        elseif ("enumerated_chast" == strtolower($unit))
        {
            $profile = "SunSpec.ChaSt.Int";
        }
        elseif ("enumerated_st" == strtolower($unit))
        {
            $profile = "SunSpec.StateCodes.Int";
        }
        elseif ("enumerated_stvnd" == strtolower($unit))
        {
            $profile = MODUL_PREFIX.".StateCodes.Int";
        }
        elseif ("enumerated_zirkulation" == strtolower($unit))
        {
            $profile = MODUL_PREFIX.".Zirkulation.Int";
        }
        elseif ("enumerated_betriebsart" == strtolower($unit))
        {
            $profile = MODUL_PREFIX.".Betriebsart.Int";
        }
        elseif ("enumerated_statsheizkreis" == strtolower($unit))
        {
            $profile = MODUL_PREFIX.".StatsHeizkreis.Int";
        }
        elseif ("enumerated_emergency-power" == strtolower($unit))
        {
            $profile = MODUL_PREFIX.".Emergency-Power.Int";
        }
        elseif ("enumerated_powermeter" == strtolower($unit))
        {
            $profile = MODUL_PREFIX.".Powermeter.Int";
        }
        elseif ("enumerated_sg-ready-status" == strtolower($unit))
        {
            $profile = MODUL_PREFIX.".SG-Ready-Status.Int";
        }
        elseif ("secs" == strtolower($unit))
        {
            $profile = "~UnixTimestamp";
        }
        elseif ("registers" == strtolower($unit)
            || "bitfield" == strtolower($unit)
            || "bitfield16" == strtolower($unit)
            || "bitfield32" == strtolower($unit)
        ) {
            $profile = false;
        }
        else
        {
            $profile = false;
            if ("" != $unit)
            {
                $this->SendDebug("getProfile()", "ERROR: Profil '".$unit."' unbekannt!", 0);
            }
        }

        return $profile;
    }

    private function checkProfiles()
    {
        $deleteProfiles_array = array();

        $this->createVarProfile(
            MODUL_PREFIX.".StateCodes.Int",
            VARIABLETYPE_INTEGER,
            '',
            0,
            0,
            0,
            0,
            0,
            array(
                array('Name' => "N/A", 'Wert' => 0, "Unbekannter Status"),
                array('Name' => "OFF", 'Wert' => 1, "Wechselrichter ist aus"),
                array('Name' => "SLEEPING", 'Wert' => 2, "Auto-Shutdown"),
                array('Name' => "STARTING", 'Wert' => 3, "Wechselrichter startet"),
                array('Name' => "MPPT", 'Wert' => 4, "Wechselrichter arbeitet normal", 'Farbe' => $this->getRgbColor("green")),
                array('Name' => "THROTTLED", 'Wert' => 5, "Leistungsreduktion aktiv", 'Farbe' => $this->getRgbColor("orange")),
                array('Name' => "SHUTTING_DOWN", 'Wert' => 6, "Wechselrichter schaltet ab"),
                array('Name' => "FAULT", 'Wert' => 7, "Ein oder mehr Fehler existieren, siehe St * oder Evt * Register", 'Farbe' => $this->getRgbColor("red")),
                array('Name' => "STANDBY", 'Wert' => 8, "Standby"),
                array('Name' => "NO_BUSINIT", 'Wert' => 9, "Keine SolarNet Kommunikation"),
                array('Name' => "NO_COMM_INV", 'Wert' => 10, "Keine Kommunikation mit Wechselrichter möglich"),
                array('Name' => "SN_OVERCURRENT", 'Wert' => 11, "Überstrom an SolarNet Stecker erkannt"),
                array('Name' => "BOOTLOAD", 'Wert' => 12, "Wechselrichter wird gerade upgedatet"),
                array('Name' => "AFCI", 'Wert' => 13, "AFCI Event (Arc-Erkennung)"),
            )
        );
        
        $this->createVarProfile(MODUL_PREFIX.".Ampere.Int", VARIABLETYPE_INTEGER, ' A');
        $this->createVarProfile(MODUL_PREFIX.".AmpereHour.Float", VARIABLETYPE_FLOAT, ' Ah');
        $this->createVarProfile(MODUL_PREFIX.".AmpereHour.Int", VARIABLETYPE_INTEGER, ' Ah');
        $this->createVarProfile(MODUL_PREFIX.".Angle.Float", VARIABLETYPE_FLOAT, ' °');
        $this->createVarProfile(MODUL_PREFIX.".Angle.Int", VARIABLETYPE_INTEGER, ' °');
        $this->createVarProfile(MODUL_PREFIX.".Blindleistung.Float", VARIABLETYPE_FLOAT, ' Var');
        $this->createVarProfile(MODUL_PREFIX.".Blindleistung.Int", VARIABLETYPE_INTEGER, ' Var');
        $this->createVarProfile(MODUL_PREFIX.".Electricity.Float", VARIABLETYPE_FLOAT, ' Wh');
        $this->createVarProfile(MODUL_PREFIX.".Electricity.Int", VARIABLETYPE_INTEGER, ' Wh');
        $this->createVarProfile(MODUL_PREFIX.".Hertz.Int", VARIABLETYPE_INTEGER, ' Hz');
//			$this->createVarProfile(MODUL_PREFIX.".Hours.Int", VARIABLETYPE_INTEGER, ' h');
//			$this->createVarProfile(MODUL_PREFIX.".MilliAmpere.Int", VARIABLETYPE_INTEGER, ' mA');
        $this->createVarProfile(MODUL_PREFIX.".Ohm.Int", VARIABLETYPE_INTEGER, ' Ohm');
        $this->createVarProfile(MODUL_PREFIX.".Scheinleistung.Float", VARIABLETYPE_FLOAT, ' VA');
        $this->createVarProfile(MODUL_PREFIX.".Scheinleistung.Int", VARIABLETYPE_INTEGER, ' VA');
        // Temperature.Float: ~Temperature
        $this->createVarProfile(MODUL_PREFIX.".Temperature.Int", VARIABLETYPE_INTEGER, ' °C');
        // Volt.Float: ~Volt
        $this->createVarProfile(MODUL_PREFIX.".Volt.Int", VARIABLETYPE_INTEGER, ' V');
//			$this->createVarProfile(MODUL_PREFIX.".Volumenstrom.Int", VARIABLETYPE_INTEGER, ' l/min');
        $this->createVarProfile(MODUL_PREFIX.".Watt.Int", VARIABLETYPE_INTEGER, ' W');

        // delete not used profiles
        foreach ($deleteProfiles_array as $profileName)
        {
            if (IPS_VariableProfileExists($profileName))
            {
                IPS_DeleteVariableProfile($profileName);
            }
        }
    }

    private function GetVariableValue(string $instanceIdent, string $variableIdent = "Value")//PHP8 : mixed
    {
        $instanceId = IPS_GetObjectIDByIdent($this->removeInvalidChars($instanceIdent), $this->InstanceID);
        $varId = IPS_GetObjectIDByIdent($this->removeInvalidChars($variableIdent), $instanceId);

        return GetValue($varId);
    }

    private function GetVariableId(string $instanceIdent, string $variableIdent = "Value"): int
    {
        $instanceId = IPS_GetObjectIDByIdent($this->removeInvalidChars($instanceIdent), $this->InstanceID);
        $varId = IPS_GetObjectIDByIdent($this->removeInvalidChars($variableIdent), $instanceId);

        return $varId;
    }

    private function GetLoggedValuesInterval(int $id, int $minutes)//PHP8 :mixed
    {
        $archiveId = IPS_GetInstanceListByModuleID("{43192F0B-135B-4CE7-A0A7-1475603F3060}");
        if (isset($archiveId[0]))
        {
            $archiveId = $archiveId[0];

            $returnValue = $this->getArithMittelOfLog($archiveId, $id, $minutes);
        }
        else
        {
            $archiveId = false;

            // no archive found
            $this->SetStatus(IS_NOARCHIVE);

            $returnValue = GetValue($id);
        }

        return $returnValue;
    }
}