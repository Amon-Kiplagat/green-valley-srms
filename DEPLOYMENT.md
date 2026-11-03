# Green Valley SRMS - Deployment Guide

This guide walks you through hosting the Green Valley School Results Management System on a production server with **PostgreSQL**.

---

## Recommended Hosting Options

### Option 1: VPS Hosting (Recommended for Full Control)

**Providers:** DigitalOcean, Linode, Vultr, AWS EC2
**Cost:** ~$6-10/month
**Best for:** Complete control over the server, PostgreSQL database, and all configurations.

#### Step-by-Step VPS Setup

**1. Provision a VPS**
- Create an account on DigitalOcean, Linode, or Vultr
- Create a new Droplet/Instance with **Ubuntu 24.04 LTS**
- Choose the smallest plan (1 CPU, 1GB RAM is sufficient for a school system)
- Select a region close to your users (e.g., Africa → Lagos, or Europe → Frankfurt)

**2. Connect to Your VPS**
```bash
ssh root@your-server-ip
```

**3. Update System & Install Dependencies**
```bash
apt update && apt upgrade -y
apt install -y nginx php php-fpm php-pgsql php-mbstring php-xml php-curl php-zip postgresql postgresql-contrib git curl
```

**4. Set Up PostgreSQL Database**
```bash
# Start PostgreSQL
systemctl enable postgresql
systemctl start postgresql

# Create database and user
sudo -u postgres psql <<EOF
CREATE USER greenvalley WITH ENCRYPTED PASSWORD 'YourStrongPassword123!';
CREATE DATABASE green_valley OWNER greenvalley;
GRANT ALL PRIVILEGES ON DATABASE green_valley TO greenvalley;
EOF
```

**5. Upload Project Files**
```bash
# On your local machine, navigate to the project directory
cd /home/mol/Downloads/green-valley-srms-main

# Clone git repo on the server (if using git)
# OR upload via SCP:
scp -r * root@your-server-ip:/var/www/green-valley/
```

**6. Install PHP Dependencies**
```bash
# On the server
cd /var/www/green-valley
curl -sS https://getcomposer.org/installer | php
php composer.phar install --no-dev --optimize-autoloader
```

**7. Configure Environment**
```bash
# On the server
cd /var/www/green-valley
cp .env.example .env
nano .env
```

Edit `.env` with your production values:
```
DB_HOST=localhost
DB_PORT=5432
DB_NAME=green_valley
DB_USER=greenvalley
DB_PASS=YourStrongPassword123!

APP_ENV=production

APP_BASE_URL=https://your-domain.com

SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=kiplagatamon22@gmail.com
SMTP_PASS=mhhk gptu exiw lcak
SMTP_FROM=kiplagatamon22@gmail.com
```

**8. Import Database Schema**
```bash
# On the server
sudo -u postgres psql -d green_valley -f /var/www/green-valley/sql/schema.sql
sudo -u postgres psql -d green_valley -f /var/www/green-valley/sql/seed.sql
sudo -u postgres psql -d green_valley -f /var/www/green-valley/sql/migration_2026_11_student_portal.sql
```

**9. Configure Nginx**
```bash
nano /etc/nginx/sites-available/green-valley
```

Paste the Nginx configuration (see "Nginx Configuration" section below).

```bash
# Enable the site
ln -s /etc/nginx/sites-available/green-valley /etc/nginx/sites-enabled/
nginx -t
systemctl restart nginx
```

**10. Set File Permissions**
```bash
chown -R www-data:www-data /var/www/green-valley
chmod -R 755 /var/www/green-valley
```

**11. Set Up SSL (HTTPS)**
```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d your-domain.com
```

**12. Configure Firewall**
```bash
ufw allow 'Nginx Full'
ufw allow OpenSSH
ufw enable
```

---

### Option 2: Render.com (Easiest, Free Tier Available)

**Cost:** Free tier available, paid plans from $7/month
**Best for:** Quick deployment, managed infrastructure, auto HTTPS.

#### Step-by-Step Render Setup

**1. Push Code to GitHub**
```bash
cd /home/mol/Downloads/green-valley-srms-main
git init
git add .
git commit -m "Initial commit"
git branch -M main
git remote add origin https://github.com/your-username/green-valley-srms.git
git push -u origin main
```

**2. Create PostgreSQL Database on Render**
- Go to [render.com](https://render.com) and sign up
- Click "New +" → "PostgreSQL"
- Give it a name (e.g., "green-valley-db")
- Choose Free tier or paid plan
- Note the **Internal Database URL** and **External Database URL**

**3. Create a Web Service on Render**
- Click "New +" → "Web Service"
- Connect your GitHub repository
- Configure:
  - **Name:** green-valley-srms
  - **Environment:** PHP
  - **Build Command:** `composer install --no-dev --optimize-autoloader`
  - **Start Command:** `php -S 0.0.0.0:$PORT`
  - **Instance Type:** Free or Starter

**4. Set Environment Variables**
In Render dashboard, add these environment variables:
```
DB_HOST=your-render-db-host
DB_PORT=5432
DB_NAME=green_valley
DB_USER=your_db_user
DB_PASS=your_db_password
APP_ENV=production
APP_BASE_URL=https://your-app.onrender.com
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=kiplagatamon22@gmail.com
SMTP_PASS=mhhk gptu exiw lcak
SMTP_FROM=kiplagatamon22@gmail.com
```

**5. Import Database Schema**
- Connect to the Render PostgreSQL database using the external URL
- Run the schema, seed, and migration SQL files

**6. Deploy**
- Render will automatically build and deploy your app
- Your app will be available at `https://your-app.onrender.com`

---

### Option 3: Railway.app (Alternative to Render)

Similar to Render, Railway offers easy deployment with PostgreSQL.

**Steps:**
1. Push code to GitHub
2. Connect GitHub repo to Railway
3. Add PostgreSQL plugin
4. Set environment variables
5. Deploy automatically

---

## Nginx Configuration

Save this as `/etc/nginx/sites-available/green-valley`:

```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    root /var/www/green-valley;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Block access to sensitive files
    location ~ /\.(env|git|ht) {
        deny all;
    }

    # Block access to vendor directory except Composer autoloader
    location ~ /vendor/ {
        deny all;
    }
    location ~ /vendor/autoload\.php$ {
        # This is included by PHP files, not accessed directly
        deny all;
    }

    # Block access to SQL directory
    location ~ /sql/ {
        deny all;
    }

    # Block access to config directory
    location ~ /config/ {
        deny all;
    }

    # Handle PHP files
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Serve static files
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Default location
    location / {
        try_files $uri $uri/ =404;
    }
}
```

---

## Docker Deployment (Optional)

For containerized deployment, use the provided `Dockerfile` and `docker-compose.yml`:

```bash
# Build and run with Docker Compose
docker-compose up -d

# The app will be available at http://localhost:8080
```

---

## Post-Deployment Checklist

- [ ] **Test login** with admin credentials
- [ ] **Test student portal** login
- [ ] **Test password reset** email (should work on production network)
- [ ] **Verify HTTPS** is working (padlock icon in browser)
- [ ] **Test all features**: students, teachers, results, reports
- [ ] **Set up database backups** (daily automated backups)
- [ ] **Configure monitoring** (uptime monitoring, error logging)
- [ ] **Update DNS** to point your domain to the server
- [ ] **Set up SSL certificate renewal** (auto-renewal with Certbot)

---

## Database Backup Strategy

**Daily automated backups:**
```bash
# Create a backup script
nano /usr/local/bin/backup-green-valley.sh
```

```bash
#!/bin/bash
BACKUP_DIR="/backups/green-valley"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p $BACKUP_DIR

pg_dump -U greenvalley green_valley > $BACKUP_DIR/backup_$DATE.sql
gzip $BACKUP_DIR/backup_$DATE.sql

# Keep only last 7 days
find $BACKUP_DIR -name "backup_*.sql.gz" -mtime +7 -delete
```

```bash
# Make executable
chmod +x /usr/local/bin/backup-green-valley.sh

# Add to crontab (daily at 2 AM)
crontab -e
# Add: 0 2 * * * /usr/local/bin/backup-green-valley.sh
```

---

## Domain Name Setup

1. **Purchase a domain** from Namecheap, GoDaddy, or Cloudflare
2. **Add an A record** pointing to your server's IP address:
   - Type: `A`
   - Name: `@` (or your domain)
   - Value: `your-server-ip`
3. **Add a www record:**
   - Type: `A`
   - Name: `www`
   - Value: `your-server-ip`
4. **Wait for DNS propagation** (up to 24 hours)

---

## Troubleshooting

### Email not sending
- Verify SMTP credentials in `.env`
- Check that port 587 is not blocked by the hosting provider
- Test with: `telnet smtp.gmail.com 587`
- Check mail logs: `tail -f /var/log/mail.log`

### Database connection errors
- Verify PostgreSQL is running: `systemctl status postgresql`
- Check `.env` database credentials
- Test connection: `psql -U greenvalley -d green_valley`

### 500 Internal Server Error
- Check PHP error log: `tail -f /var/log/php/php8.3-fpm.log`
- Check Nginx error log: `tail -f /var/log/nginx/error.log`
- Verify file permissions: `chown -R www-data:www-data /var/www/green-valley`

### SSL certificate issues
- Renew certificate: `certbot renew`
- Check certificate status: `certbot certificates`

---

## Security Recommendations

1. **Change default passwords** for admin and all sample accounts
2. **Enable firewall** (UFW on Ubuntu)
3. **Use SSH keys** instead of password authentication
4. **Keep system updated**: `apt update && apt upgrade -y`
5. **Set up fail2ban** to prevent brute force attacks
6. **Regular database backups**
7. **Monitor logs** for suspicious activity
8. **Disable directory listing** in Nginx
9. **Use strong passwords** for all accounts
10. **Keep `.env` file secure** (never commit to git)

---

## Support

For issues or questions:
- Check the troubleshooting section above
- Review error logs on the server
- Contact your hosting provider's support
