# 503c Assistant - Production Deployment Checklist

This checklist is provided by the QA specialist to ensure a safe and secure production deployment of the 503c Assistant application.

## Critical Security Issues Found

### 1. ~~Hardcoded Credentials in setup-production.sh~~ (RESOLVED)
**Severity: CRITICAL**
**Location:** `/ops/db/setup-production.sh`

**Status: FIXED.** The script reads DB_PASS from an environment variable or
interactive prompt. No credentials are hardcoded. SQL is written to a
chmod-600 temp file (cleaned up on exit) so passwords never appear in process
argument lists. The Python `.env` writer receives credentials via environment
variables, not shell-interpolated source code.

Provide DB settings via environment variables:

```bash
export DB_NAME="irb_503c_assistant"
export DB_USER="your_db_user"
export DB_HOST="localhost"
export DB_PORT="3306"
export DB_PASS="your_strong_password"
```

**Action Required:**
- [x] Confirm no credentials are hardcoded in the script
- [x] Use environment variables or prompt for DB_PASS instead
- [ ] Generate strong, random passwords for production

### 2. ~~Hardcoded Sudo Password~~ (RESOLVED)
**Severity: CRITICAL**
**Location:** `/ops/db/setup-production.sh`

**Status: FIXED.** The script has never contained a hardcoded sudo password.
It uses `sudo systemctl ...` and `sudo mariadb -u root`, which invoke the
system's standard sudo authentication. No passwords are embedded or piped.

**Action Required:**
- [x] Remove all hardcoded passwords immediately
- [x] Script should ask user to run sudo commands manually or use proper privilege escalation

### 3. Current .env File Contains Production Credentials
**Severity: CRITICAL**
**Location:** `/.env` line 3

Do not commit or share production secrets.

Examples of sensitive values that must NOT be committed:
- APP_KEY=base64:... (generated via `php artisan key:generate`)
- DB_PASSWORD=... (strong random password)

**Action Required:**
- [ ] Generate new APP_KEY for production: `php artisan key:generate`
- [ ] Generate strong, random DB_PASSWORD
- [ ] Ensure .env is NOT committed to version control
- [ ] Verify .env is in .gitignore

### 4. Debug Mode Enabled in .env
**Severity: HIGH**
**Location:** `/.env` line 4

APP_DEBUG=true in current configuration

**Action Required:**
- [ ] Set APP_DEBUG=false for production

---

## Pre-Deployment Checklist

### Repository Secret Scan

- [ ] Run a quick scan for secret-like strings (from repo root):

```bash
git grep -nI -E '(password|passwd|secret|token|api[_-]?key|DB_PASS=|DB_PASSWORD=|APP_KEY=base64:|AKIA[0-9A-Z]{16}|-----BEGIN [A-Z ]*PRIVATE KEY-----)' -- 503c-assistant/ops/
```

### Environment Configuration
- [ ] Copy `.env.example` to `.env` (do not use existing .env)
- [ ] Generate new APP_KEY: `php artisan key:generate`
- [ ] Set APP_ENV=production
- [ ] Set APP_DEBUG=false
- [ ] Configure APP_URL with actual production domain
- [ ] Update TRUSTED_PROXIES in bootstrap/app.php if needed

### Database Setup
- [x] Review and modify `/ops/db/setup-production.sh` to remove hardcoded credentials
- [ ] Create strong, unique database credentials
- [ ] Create production database
- [ ] Create test database
- [ ] Run migrations: `php artisan migrate`
- [ ] Run seeders: `php artisan db:seed`
- [ ] Change default admin password immediately after seeding

### SSL Certificates
- [ ] Obtain valid SSL certificate for production domain
- [ ] Update certificate paths in Apache config:
  - SSLCertificateFile
  - SSLCertificateKeyFile
- [ ] For Let's Encrypt, use certbot paths
- [ ] For self-signed testing only, note that browsers will warn

### Apache Configuration
- [ ] Copy `/ops/apache/503c-assistant-production.conf` to Apache sites-available
- [ ] Update ServerName from "irb.example.org" to actual domain
- [ ] Update ServerAdmin email address
- [ ] Update certificate paths to actual certificate locations
- [ ] Update IncludeOptional path for security-headers.conf to absolute path
- [ ] Update log paths if using custom locations
- [ ] Enable required modules:
  - `sudo a2enmod rewrite`
  - `sudo a2enmod ssl`
  - `sudo a2enmod proxy`
  - `sudo a2enmod proxy_http`
  - `sudo a2enmod proxy_wstunnel` (if using websockets)
  - `sudo a2enmod headers`
- [ ] Enable site: `sudo a2ensite 503c-assistant-production.conf`
- [ ] Test configuration: `sudo apachectl configtest`
- [ ] Reload Apache: `sudo systemctl reload apache2`

### Application Server
- [ ] Decide on production server method:
  - Option A: php-fpm with Apache (recommended for production)
  - Option B: php artisan serve with systemd service (acceptable for smaller deployments)
- [ ] If using artisan serve, create systemd service file
- [ ] Configure service to auto-start on boot
- [ ] Set appropriate file permissions
- [ ] Ensure storage directory is writable

### File Permissions
- [ ] Set proper ownership: `sudo chown -R www-data:www-data storage bootstrap/cache`
- [ ] Set proper permissions:
  - `chmod -R 775 storage`
  - `chmod -R 775 bootstrap/cache`
- [ ] Verify .env file has restricted permissions: `chmod 600 .env`

### Security Headers Verification
- [ ] Verify security-headers.conf is included
- [ ] Test headers using: `curl -I https://your-domain.com`
- [ ] Verify HSTS header is present
- [ ] Verify X-Frame-Options: DENY
- [ ] Verify CSP header is present

### Cron Jobs
- [ ] Copy `/ops/cron/503c-assistant.crontab.example` to crontab
- [ ] Update paths to absolute paths
- [ ] Add to crontab: `crontab -e`
- [ ] Verify scheduler runs: check `storage/logs/scheduler.log`

### Log Configuration
- [ ] Set LOG_LEVEL appropriate for production (info or warning, not debug)
- [ ] Configure log rotation
- [ ] Ensure logs are not accessible via web

---

## Post-Deployment Checklist

### Basic Functionality Tests
- [ ] Access site via HTTPS - should redirect from HTTP
- [ ] Login page loads correctly
- [ ] Admin login works with seeded credentials
- [ ] Change default admin password
- [ ] Create a test project
- [ ] Upload a test document
- [ ] Verify document processing works
- [ ] Check audit logs are recording events

### Security Verification
- [ ] Verify APP_DEBUG is off (no stack traces in browser)
- [ ] Verify .env is not accessible via web
- [ ] Verify storage directory is not accessible via web
- [ ] Check SSL certificate is valid
- [ ] Test security headers: https://securityheaders.com
- [ ] Verify all sensitive data is encrypted at rest (if implemented)
- [ ] Verify database connections use TLS/SSL (for remote DB)

### Performance Checks
- [ ] Check page load times
- [ ] Monitor memory usage
- [ ] Check disk space for logs and uploads
- [ ] Verify database query performance

### Monitoring Setup
- [ ] Set up log monitoring
- [ ] Set up disk space alerts
- [ ] Set up service monitoring (Apache, database)
- [ ] Configure backup strategy for database
- [ ] Configure backup strategy for uploaded files

### Documentation
- [ ] Document all production credentials securely
- [ ] Document backup and restore procedures
- [ ] Document deployment rollback procedure
- [ ] Document contact information for issues

---

## Rollback Procedure

If critical issues are found after deployment:

1. **Immediate Actions:**
   - [ ] Stop Apache: `sudo systemctl stop apache2`
   - [ ] Stop application server
   - [ ] Switch to previous working version

2. **Database Rollback:**
   - [ ] Restore database from backup taken before migration

3. **Configuration Rollback:**
   - [ ] Restore previous .env file
   - [ ] Restore previous Apache config

4. **Verification:**
   - [ ] Test application functionality
   - [ ] Verify data integrity

---

## Important Notes

1. **Never commit `.env` file** to version control
2. **Never use production credentials** in setup scripts
3. **Always test in staging** before production deployment
4. **Keep backups** before any migration or configuration change
5. **Monitor logs** for the first week after deployment
6. **Schedule regular security updates** for OS and dependencies

---

## Configuration File Locations

| File | Purpose | Action Required |
|------|---------|-----------------|
| `/ops/apache/503c-assistant-production.conf` | Apache vhost | Update domain, cert paths, include path |
| `/ops/apache/snippets/security-headers.conf` | Security headers | Review, may need CSP updates |
| `/ops/db/setup-production.sh` | Database setup | Credentials via env vars / prompt (fixed) |
| `/ops/db/start.sh` | Local MariaDB (dev) | Not for production use |
| `/ops/cron/503c-assistant.crontab.example` | Scheduler | Update paths, add to crontab |
| `/.env.example` | Environment template | Use as basis for production .env |
| `/.env` | Current environment | **DO NOT USE** - contains exposed credentials |

---

## Contact Information

For issues or questions about deployment:
- System Administrator: [To be configured]
- Security Lead: [To be configured]
- Application Lead: [To be configured]

---

**Last Updated:** 2026-02-07
**Reviewed By:** QA Specialist (team-quality)
**Status:** Script credential issues resolved - Complete remaining checklist items before deploying
