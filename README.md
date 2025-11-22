# VLAN Selector - RADIUS Service for MAC Authentication

A RADIUS service for MAC authentication with VLAN assignment. This service authenticates devices based on their MAC address and assigns them to configured VLANs.

## Features

- MAC address authentication via RADIUS
- VLAN assignment based on MAC address mapping
- YAML-based configuration
- Built with PHP and ReactPHP
- Uses SkyRadius library for RADIUS protocol implementation

## Requirements

- PHP >= 7.4
- Composer

## Installation

1. Clone the repository:
```bash
git clone https://github.com/skydiablo/vlan-selector.git
cd vlan-selector
```

2. Install dependencies:
```bash
composer install
```

3. Copy the `mac-vlan-config.example.yaml` file to `mac-vlan-config.yaml` and add your own configuration:
```yaml
mapping:
  100:  # VLAN-ID
    - "00:11:22:33:44:55"
    - "aa:bb:cc:dd:ee:ff"
  200:  # VLAN-ID
    - "001122334455"
    - "aabbccddeeff"
  default: 1  # default VLAN-ID for unknown MAC addresses

radius:
  port: 1812
  secret: "testing123"
```

## Usage

### Start the RADIUS Server

```bash
php server.php
```

Or use the composer script:
```bash
composer start
```

The server will start listening on the configured port (default: 1812).

For MAC authentication, the AP typically sends the MAC address in the `Calling-Station-Id` attribute. Some APs may also send it as `User-Name`.

## Configuration

### MAC Address Format

MAC addresses can be specified in any of these formats:
- `00:11:22:33:44:55` (with colons)
- `00-11-22-33-44-55` (with dashes)
- `001122334455` (without separators)

The service automatically normalizes all formats.

### VLAN Assignment

The service returns the following RADIUS attributes for VLAN assignment:
- `Tunnel-Type` = VLAN (13)
- `Tunnel-Medium-Type` = IEEE-802 (6)
- `Tunnel-Private-Group-Id` = VLAN ID

These attributes are standard for VLAN assignment according to RFC2868.

## How It Works

1. Device connects to WLAN AP
2. AP sends RADIUS Access-Request with MAC address (typically in `Calling-Station-Id`)
3. Service checks MAC address against configured VLAN groups
4. If MAC is found in a VLAN group:
   - Returns `Access-Accept` with VLAN assignment attributes
5. If MAC is not found:
   - Returns `Access-Accept` with default VLAN (if `default` is configured)
   - Otherwise returns `Access-Reject`

## AP Configuration

Configure your WLAN Access Point to:
- Use RADIUS authentication
- Point to this server's IP and port
- Use the configured RADIUS secret
- Enable MAC authentication

Example AP configuration (varies by vendor):
- RADIUS Server: `<server-ip>:1812`
- RADIUS Secret: `testing123`
- Authentication Method: MAC Authentication

## License

MIT

