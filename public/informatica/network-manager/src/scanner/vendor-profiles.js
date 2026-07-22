/**
 * Perfiles SNMP específicos por marca de Switch
 * Contiene los OIDs necesarios para extraer la tabla MAC y mapearla a puertos.
 */

const OIDS = {
    // Standard MIB-II
    sysDescr: '1.3.6.1.2.1.1.1.0',
    sysName: '1.3.6.1.2.1.1.5.0',

    // dot1dTpFdbTable (MAC Address Table estándar - BRIDGE-MIB)
    // 1.3.6.1.2.1.17.4.3.1.1 (mac address)
    // 1.3.6.1.2.1.17.4.3.1.2 (port number — bridge port, NOT ifIndex!)
    dot1dTpFdbAddress: '1.3.6.1.2.1.17.4.3.1.1',
    dot1dTpFdbPort:    '1.3.6.1.2.1.17.4.3.1.2',

    // CRITICAL: bridge port → ifIndex mapping (dot1dBasePortIfIndex)
    // Without this, bridge port numbers are used as ifIndex — WRONG for CSS326/Cisco/etc.
    // CSS326 SwOS has lo + bridge interfaces that offset ifIndex from bridge port number.
    dot1dBasePortIfIndex: '1.3.6.1.2.1.17.1.4.1.2',

    // Interfaces (IF-MIB)
    ifName: '1.3.6.1.2.1.31.1.1.1.1',
    ifDescr: '1.3.6.1.2.1.2.2.1.2',
    ifAlias: '1.3.6.1.2.1.31.1.1.1.18', // Description manual

    // Traffic counters (IF-MIB)
    ifInOctets:  '1.3.6.1.2.1.2.2.1.10',
    ifOutOctets: '1.3.6.1.2.1.2.2.1.16',

    // Port state & speed (IF-MIB)
    ifOperStatus: '1.3.6.1.2.1.2.2.1.8',   // 1=up 2=down 3=testing
    ifSpeed:      '1.3.6.1.2.1.2.2.1.5',   // bps (32-bit, max ~4Gbps)
    ifHighSpeed:  '1.3.6.1.2.1.31.1.1.1.15', // Mbps (for >=1G ports)

    // VLANs (Q-BRIDGE-MIB)
    dot1qVlanStaticName:         '1.3.6.1.2.1.17.7.1.4.3.1.1',
    dot1qVlanStaticEgressPorts:  '1.3.6.1.2.1.17.7.1.4.3.1.2',
    dot1qVlanStaticUntaggedPorts:'1.3.6.1.2.1.17.7.1.4.3.1.4',

    // Interface IP addresses (IP-MIB)
    ipAdEntIfIndex: '1.3.6.1.2.1.4.20.1.2',
    ipAdEntNetMask: '1.3.6.1.2.1.4.20.1.3',

    // ARP table (IP-MIB ipNetToMediaTable)
    ipNetToMediaPhysAddress: '1.3.6.1.2.1.4.22.1.2',

    // Routing table (IP-MIB ipRouteTable — deprecated but widely supported)
    ipRouteDest:    '1.3.6.1.2.1.4.21.1.1',
    ipRouteNextHop: '1.3.6.1.2.1.4.21.1.7',
    ipRouteMask:    '1.3.6.1.2.1.4.21.1.11',
    ipRouteType:    '1.3.6.1.2.1.4.21.1.8',  // 1=other 2=invalid 3=direct 4=indirect
    ipRouteMetric:  '1.3.6.1.2.1.4.21.1.3',

    // CDP — Cisco Discovery Protocol (CISCO-CDP-MIB)
    cdpCacheDeviceId: '1.3.6.1.4.1.9.9.23.1.2.1.1.6',
    cdpCacheAddress:  '1.3.6.1.4.1.9.9.23.1.2.1.1.4',
    cdpCachePortId:   '1.3.6.1.4.1.9.9.23.1.2.1.1.7',
};

const PROFILES = {
    cisco: {
        match: /Cisco|IOS/i,
        name: 'Cisco',
        getMacTableOid: () => OIDS.dot1dTpFdbPort,
        getPortNameOid: () => OIDS.ifName,
        hasCdp: true,
    },
    mikrotik: {
        match: /MikroTik|RouterOS/i,
        name: 'MikroTik',
        getMacTableOid: () => OIDS.dot1dTpFdbPort,
        getPortNameOid: () => OIDS.ifDescr
    },
    tplink: {
        match: /TP-LINK|JetStream/i,
        name: 'TP-Link',
        getMacTableOid: () => OIDS.dot1dTpFdbPort,
        getPortNameOid: () => OIDS.ifDescr
    },
    tenda: {
        match: /Tenda/i,
        name: 'Tenda',
        getMacTableOid: () => OIDS.dot1dTpFdbPort,
        getPortNameOid: () => OIDS.ifDescr
    },
    generic: {
        match: /.*/,
        name: 'Generic/Standard',
        getMacTableOid: () => OIDS.dot1dTpFdbPort,
        getPortNameOid: () => OIDS.ifDescr
    }
};

/**
 * Detecta el perfil del vendor basado en el 'sysDescr' del equipo
 */
function detectProfile(sysDescr) {
    for (const key of Object.keys(PROFILES)) {
        if (key === 'generic') continue;
        if (PROFILES[key].match.test(sysDescr)) {
            return PROFILES[key];
        }
    }
    return PROFILES.generic;
}

module.exports = {
    OIDS,
    PROFILES,
    detectProfile
};
