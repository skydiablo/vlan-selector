<?php

namespace SkyDiablo\VLANSelector;

use SkyDiablo\SkyRadius\Attribute\AttributeInterface;
use SkyDiablo\SkyRadius\Attribute\TunnelAttribute;
use SkyDiablo\SkyRadius\Connection\Context;
use SkyDiablo\SkyRadius\Packet\PacketInterface;
use SkyDiablo\SkyRadius\Packet\RequestPacket;
use Symfony\Component\Yaml\Yaml;

/**
 * Handler for MAC authentication and VLAN assignment
 */
class MacVlanHandler
{
    private array $vlanMacMapping;
    private ?int $defaultVlan;
    private string $configFile;

    public function __construct(string $configFile = 'mac-vlan-config.yaml')
    {
        $this->configFile = $configFile;
        $this->loadConfig();
    }

    /**
     * Load VLAN to MAC mapping from YAML file
     */
    private function loadConfig(): void
    {
        if (!file_exists($this->configFile)) {
            throw new \RuntimeException("Configuration file not found: {$this->configFile}");
        }

        $config = Yaml::parseFile($this->configFile);
        $mapping = $config['mapping'] ?? [];

        // Extract default VLAN if present
        $this->defaultVlan = $mapping['default'] ?? null;

        // Build reverse mapping: MAC -> VLAN for faster lookup
        $this->vlanMacMapping = [];
        foreach ($mapping as $vlan => $macList) {
            // Skip 'default' key
            if ($vlan === 'default') {
                continue;
            }

            // Ensure VLAN is an integer
            $vlanId = is_numeric($vlan) ? (int)$vlan : null;
            if ($vlanId === null) {
                continue;
            }

            // Normalize and store MAC addresses for this VLAN
            if (is_array($macList)) {
                foreach ($macList as $mac) {
                    $normalizedMac = $this->normalizeMacAddress($mac);
                    $this->vlanMacMapping[$normalizedMac] = $vlanId;
                }
            }
        }
    }

    /**
     * Normalize MAC address (remove colons/dashes, convert to lowercase)
     */
    private function normalizeMacAddress(string $mac): string
    {
        return strtolower(str_replace([':', '-', ' '], '', $mac));
    }

    /**
     * Extract MAC address from RADIUS request
     * MAC can be in Calling-Station-Id or User-Name attribute
     */
    private function extractMacAddress(RequestPacket $request): ?string
    {
        // Try Calling-Station-Id first (standard for MAC authentication)
        $callingStationId = $request->getAttribute('Calling-Station-Id')[0] ?? null;
        if ($callingStationId) {
            return $this->normalizeMacAddress($callingStationId->getValue());
        }

        // Fallback to User-Name (some APs send MAC as username)
        $userName = $request->getAttribute('User-Name')[0] ?? null;
        if ($userName) {
            $value = $userName->getValue();
            // Check if it looks like a MAC address (with or without separators)
            if (preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $value)
                || preg_match('/^[0-9A-Fa-f]{12}$/', $value)
            ) {
                return $this->normalizeMacAddress($value);
            }
        }

        return null;
    }

    /**
     * Get VLAN ID for MAC address
     */
    private function getVlanForMac(string $mac): ?int
    {
        $normalizedMac = $this->normalizeMacAddress($mac);

        return $this->vlanMacMapping[$normalizedMac] ?? $this->defaultVlan;
    }

    /**
     * Handle RADIUS authentication request
     */
    public function handleRequest(Context $context): void
    {
        $macAddress = $this->extractMacAddress($context->getRequest());

        if (!$macAddress) {
            // No MAC address found, reject
            $context->getResponse()->setType(PacketInterface::ACCESS_REJECT);
            return;
        }

        $vlanId = $this->getVlanForMac($macAddress);

        if ($vlanId === null) {
            // MAC not in list and no default VLAN, reject
            $context->getResponse()->setType(PacketInterface::ACCESS_REJECT);
            return;
        }

        // Always return Access-Accept with VLAN assignment
        $context->getResponse()->setType(PacketInterface::ACCESS_ACCEPT);

        // Add VLAN assignment attributes
        // RFC2868: Tunnel-Private-Group-Id is used for VLAN assignment
        // Format: "VLANID" or just the VLAN ID number
        $context->getResponse()->addAttribute(
            new TunnelAttribute(AttributeInterface::ATTR_TUNNEL_PRIVATE_GROUP_ID, 0, $vlanId), // Tunnel-Private-Group-Id = 81
        );

        // Some APs also require Tunnel-Type = VLAN (13)
        $context->getResponse()->addAttribute(
            new TunnelAttribute(AttributeInterface::ATTR_TUNNEL_TYPE, 0, 13), // Tunnel-Type = 64, value 13 = VLAN
        );

        // Tunnel-Medium-Type = IEEE-802 (6)
        $context->getResponse()->addAttribute(
            new TunnelAttribute(AttributeInterface::ATTR_TUNNEL_MEDIUM_TYPE, 0, 6), // Tunnel-Medium-Type = 65, value 6 = IEEE-802
        );
    }

    /**
     * Reload configuration from file
     */
    public function reloadConfig(): void
    {
        $this->loadConfig();
    }
}

