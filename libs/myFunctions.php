<?php

declare(strict_types=1);

if (!defined('VARIABLETYPE_BOOLEAN')) {
    define('VARIABLETYPE_BOOLEAN', 0);
    define('VARIABLETYPE_INTEGER', 1);
    define('VARIABLETYPE_FLOAT', 2);
    define('VARIABLETYPE_STRING', 3);
}

if (!defined('KL_DEBUG')) {
    define('KL_DEBUG', 10206);		// Debugmeldung (werden ausschliesslich ins Log geschrieben. Bei Deaktivierung des Spezialschalter "LogfileVerbose" werden diese nichtmal ins Log geschrieben.)
    define('KL_ERROR', 10206);		// Fehlermeldung
    define('KL_MESSAGE', 10201);	// Nachricht
    define('KL_NOTIFY', 10203);		// Benachrichtigung
    define('KL_WARNING', 10204);	// Warnung
}

if (!defined('IS_NOARCHIVE')) {
    if (!defined('IS_EBASE')) {
        define('IS_EBASE', 200);
    }

    define('IS_NOARCHIVE', IS_EBASE + 1);
    define('IS_IPPORTERROR', IS_EBASE + 2);
}

// ModBus RTU TCP
if (!defined('MODBUS_INSTANCES')) {
    define("MODBUS_INSTANCES", "{A5F663AB-C400-4FE5-B207-4D67CC030564}");
}
if (!defined('CLIENT_SOCKETS')) {
    define("CLIENT_SOCKETS", "{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}");
}
if (!defined('MODBUS_ADDRESSES')) {
    define("MODBUS_ADDRESSES", "{CB197E50-273D-4535-8C91-BB35273E3CA5}");
}

if (!defined('MODBUSDATATYPE_BIT')) {
    define('MODBUSDATATYPE_BIT', 1);
    define('MODBUSDATATYPE_WORD', 2);
    define('MODBUSDATATYPE_DWORD', 3);
    define('MODBUSDATATYPE_CHAR', 4);
    define('MODBUSDATATYPE_SHORT', 5);
    define('MODBUSDATATYPE_INT', 6);
    define('MODBUSDATATYPE_REAL', 7);
    define('MODBUSDATATYPE_INT64', 8);
    define('MODBUSDATATYPE_REAL64', 9);
    define('MODBUSDATATYPE_STRING', 10);
}


trait myFunctions
{
}
