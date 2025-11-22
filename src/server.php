<?php

namespace SkyDiablo\VLANSelector;

require_once __DIR__.'/../vendor/autoload.php';

use SkyDiablo\SkyRadius\Attribute\AttributeInterface;
use SkyDiablo\SkyRadius\Connection\Context;
use SkyDiablo\SkyRadius\Packet\PacketInterface;
use SkyDiablo\SkyRadius\SkyRadius;
use SkyDiablo\SkyRadius\SkyRadiusServer;

// Load configuration
$configFile = __DIR__.'/../mac-vlan-config.yaml';
if (!file_exists($configFile)) {
    die("Configuration file not found: {$configFile}\n");
}

$config = \Symfony\Component\Yaml\Yaml::parseFile($configFile);
$port = $config['radius']['port'] ?? 1812;
$secret = $config['radius']['secret'] ?? 'testing123';

// Initialize MAC/VLAN handler
$macVlanHandler = new MacVlanHandler($configFile);

// Create socket server
$socket = "0.0.0.0:{$port}";

// Create RADIUS server
$radiusServer = new SkyRadiusServer($socket, $secret);

// Handle incoming RADIUS requests
$radiusServer->on(SkyRadius::EVENT_PACKET, function (Context $context) use ($macVlanHandler) {
    echo "[".date('Y-m-d H:i:s')."] Received RADIUS request\n";

    // Log request details
    $callingStationId = $context->getRequest()->getAttribute('Calling-Station-Id')[0] ?? null;
    $userName = $context->getRequest()->getAttribute('User-Name')[0] ?? null;

    if ($callingStationId) {
        echo "  Calling-Station-Id: ".$callingStationId->getValue()."\n";
    }
    if ($userName) {
        echo "  User-Name: ".$userName->getValue()."\n";
    }

    // Process request
    $macVlanHandler->handleRequest($context);

    // Log response
    if ($context->getResponse()->getType() === PacketInterface::ACCESS_ACCEPT) {
        echo "  -> Access-Accept";
        $vlanAttr = $context->getResponse()->getAttribute(AttributeInterface::ATTR_TUNNEL_PRIVATE_GROUP_ID)[0] ?? null; // Tunnel-Private-Group-Id
        if ($vlanAttr) {
            echo " (VLAN: ".$vlanAttr->getValue().")";
        }
        echo "\n";
    } else {
        echo "  -> Access-Reject\n";
    }
    echo "\n";
});

echo "RADIUS Server started on port {$port}\n";
echo "Waiting for requests...\n\n";

