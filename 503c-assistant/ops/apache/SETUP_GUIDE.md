# Apache Reverse Proxy Setup Guide for 503c Assistant

This guide provides step-by-step instructions for configuring Apache as a reverse proxy for the 503c Assistant application.

## Prerequisites

- Ubuntu/Debian Linux system with Apache 2.4+
- Sudo access (interactive)
- Laravel application located at `/home/juhur/PROJECTS/project_IRB-assist/503c-assistant`

## Step 1: Enable Required Apache Modules

Enable the necessary Apache modules for reverse proxy and SSL:

```bash
sudo a2enmod rewrite
sudo a2enmod ssl
sudo a2enmod headers
sudo a2enmod proxy
sudo a2enmod proxy_http
sudo a2enmod proxy_wstunnel
sudo a2enmod proxy_balancer
sudo a2enmod lbmethod_byrequests

# Restart Apache to load modules
sudo systemctl restart apache2
```

## Step 2: Generate Self-Signed SSL Certificate

For testing purposes, generate a self-signed SSL certificate:

```bash
# Create SSL certificate directory
sudo mkdir -p /etc/ssl/certs
sudo mkdir -p /etc/ssl/private

# Generate self-signed certificate (valid for 365 days)
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/private/503c-assistant-selfsigned.key \
  -out /etc/ssl/certs/503c-assistant-selfsigned.crt \
  -subj "/C=US/ST=State/L=City/O=Organization/CN=irb.example.org"

# Set proper permissions
sudo chmod 600 /etc/ssl/private/503c-assistant-selfsigned.key
sudo chmod 644 /etc/ssl/certs/503c-assistant-selfsigned.crt
```

**Note:** Replace the certificate details (C, ST, L, O, CN) with your actual information. The Common Name (CN) should match your ServerName in the Apache configuration.

## Step 3: Deploy Apache Configuration

Copy the production configuration to Apache sites-available:

```bash
# Copy the configuration file
sudo cp /home/juhur/PROJECTS/project_IRB-assist/503c-assistant/ops/apache/503c-assistant-production.conf \
  /etc/apache2/sites-available/503c-assistant.conf

# Update ServerName to match your actual domain or IP
sudo nano /etc/apache2/sites-available/503c-assistant.conf
# Change: ServerName irb.example.org
# To: ServerName your-actual-domain.com or ServerName YOUR_SERVER_IP
```

## Step 4: Enable the Site and Restart Apache

```bash
# Enable the site
sudo a2ensite 503c-assistant.conf

# Test Apache configuration for syntax errors
sudo apache2ctl configtest

# If test passes, restart Apache
sudo systemctl restart apache2

# Check Apache status
sudo systemctl status apache2
```

## Step 5: Configure Laravel for Proxy

Update Laravel configuration to trust the reverse proxy:

```bash
# Edit Laravel .env file
cd /home/juhur/PROJECTS/project_IRB-assist/503c-assistant
nano .env

# Add/set these variables:
APP_URL=https://irb.example.org
# Replace with your actual domain or IP

# Configure Laravel to trust proxies
echo 'APP_TRUSTED_PROxies=*' >> .env

# For Laravel 8+ with fideloper/proxy package (usually included):
# The proxy configuration is already set up in config/trustedproxy.php
```

## Step 6: Start the Laravel Application

Start the Laravel development server (for testing):

```bash
cd /home/juhur/PROJECTS/project_IRB-assist/503c-assistant

# Start Laravel development server
php artisan serve --host=127.0.0.1 --port=8000

# For production, consider using systemd service for auto-start
# See the section below
```

## Step 7: Configure Firewall (if enabled)

```bash
# Allow HTTP and HTTPS traffic
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Check firewall status
sudo ufw status
```

## Step 8: Test the Configuration

1. Open your browser and navigate to `https://irb.example.org` (or your configured domain)
2. Accept the self-signed certificate warning (for testing only)
3. You should see the 503c Assistant application

## Optional: Production Setup with Systemd

For production, create a systemd service to run Laravel automatically:

```bash
# Create systemd service file
sudo nano /etc/systemd/system/503c-assistant.service
```

Add the following content:

```ini
[Unit]
Description=503c Assistant Laravel Application
After=network.target

[Service]
Type=simple
User=juhur
Group=juhur
WorkingDirectory=/home/juhur/PROJECTS/project_IRB-assist/503c-assistant
ExecStart=/usr/bin/php /home/juhur/PROJECTS/project_IRB-assist/503c-assistant/artisan serve --host=127.0.0.1 --port=8000
Restart=always
RestartSec=10
Environment=APP_ENV=production

[Install]
WantedBy=multi-user.target
```

Enable and start the service:

```bash
sudo systemctl daemon-reload
sudo systemctl enable 503c-assistant
sudo systemctl start 503c-assistant
sudo systemctl status 503c-assistant
```

## Troubleshooting

### Apache fails to start

```bash
# Check Apache error log
sudo tail -f /var/log/apache2/error.log

# Check site-specific error log
sudo tail -f /var/log/apache2/503c-assistant-error.log
```

### SSL Certificate errors

```bash
# Verify certificate files exist
ls -la /etc/ssl/certs/503c-assistant-selfsigned.crt
ls -la /etc/ssl/private/503c-assistant-selfsigned.key

# Check certificate details
openssl x509 -in /etc/ssl/certs/503c-assistant-selfsigned.crt -text -noout
```

### Proxy connection errors

```bash
# Verify Laravel is running on port 8000
curl http://127.0.0.1:8000

# Check Apache proxy modules are enabled
apache2ctl -M | grep proxy
```

### Permission issues

```bash
# Ensure Apache can read application files
sudo chmod -R 755 /home/juhur/PROJECTS/project_IRB-assist/503c-assistant

# Ensure storage and cache are writable
chmod -R 775 /home/juhur/PROJECTS/project_IRB-assist/503c-assistant/storage
chmod -R 775 /home/juhur/PROJECTS/project_IRB-assist/503c-assistant/bootstrap/cache
```

## Security Notes

1. **Self-Signed Certificates**: For testing only. Use Let's Encrypt or a commercial CA for production.
2. **Update ServerName**: Replace `irb.example.org` with your actual domain name.
3. **Firewall**: Ensure only necessary ports (80, 443) are exposed.
4. **Production Server**: Consider using php-fpm + Nginx/Apache instead of `php artisan serve` for better performance.

## Apache Commands Reference

```bash
# Enable/Disable sites
sudo a2ensite 503c-assistant.conf
sudo a2dissite 503c-assistant.conf

# Enable/Disable modules
sudo a2enmod <module-name>
sudo a2dismod <module-name>

# Test configuration
sudo apache2ctl configtest

# Restart/Reload Apache
sudo systemctl restart apache2
sudo systemctl reload apache2

# Check Apache status
sudo systemctl status apache2
```

## Configuration File Locations

- Apache config: `/etc/apache2/sites-available/503c-assistant.conf`
- SSL Certificate: `/etc/ssl/certs/503c-assistant-selfsigned.crt`
- SSL Key: `/etc/ssl/private/503c-assistant-selfsigned.key`
- Error logs: `/var/log/apache2/503c-assistant-error.log`
- Access logs: `/var/log/apache2/503c-assistant-access.log`
