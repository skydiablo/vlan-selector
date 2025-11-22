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

Or use Docker (recommended for production).

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

### Using Docker

#### Pull from Docker Hub (Recommended)

Pre-built Docker images are available on Docker Hub:

```bash
docker pull skydiablo/vlan-selector:latest
```

Or use a specific version:
```bash
docker pull skydiablo/vlan-selector:v1.0.0
```

#### Build and Run with Docker Compose (Recommended)

1. Ensure `mac-vlan-config.yaml` is configured (see Configuration section above)

2. Start the container:
```bash
docker-compose up -d
```

3. View logs:
```bash
docker-compose logs -f
```

4. Stop the container:
```bash
docker-compose down
```

#### Build and Run with Docker

1. Build the Docker image:
```bash
docker build -t vlan-selector .
```

2. Run the container:
```bash
docker run -d \
  --name vlan-selector \
  -p 1812:1812/udp \
  -v $(pwd)/mac-vlan-config.yaml:/app/mac-vlan-config.yaml:ro \
  vlan-selector
```

3. View logs:
```bash
docker logs -f vlan-selector
```

4. Stop the container:
```bash
docker stop vlan-selector
docker rm vlan-selector
```

**Note:** The configuration file is mounted as read-only (`:ro`) to allow updates without rebuilding the image. After updating `mac-vlan-config.yaml`, restart the container for changes to take effect.

#### Using Pre-built Image from Docker Hub

You can also use the pre-built image directly without building:

```bash
docker run -d \
  --name vlan-selector \
  -p 1812:1812/udp \
  -v $(pwd)/mac-vlan-config.yaml:/app/mac-vlan-config.yaml:ro \
  skydiablo/vlan-selector:latest
```

## CI/CD

This project uses GitHub Actions to automatically build and publish Docker images to Docker Hub:

- **On push to main/master**: Builds and pushes image tagged as `latest`
- **On tag push (v*)**: Builds and pushes image with semantic version tags (e.g., `v1.0.0`, `1.0.0`, `1.0`, `1`)

### Required GitHub Secrets

To enable automatic publishing, configure the following secrets in your GitHub repository:

- `DOCKER_USERNAME`: Your Docker Hub username
- `DOCKER_PASSWORD`: Your Docker Hub password or access token

**Note:** Using an access token instead of a password is recommended for better security.

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

