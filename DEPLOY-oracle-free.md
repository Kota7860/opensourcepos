# Deploy OSPOS free on an Oracle Cloud "Always Free" server

This guide gets OSPOS running on a **free, always-on** cloud server with
**automatic HTTPS**, so you can open it on your phone and install it as an app
(PWA). It uses this repository's built-in Docker + nginx + Let's Encrypt setup.

> Let's Encrypt issues HTTPS certificates for a **domain name**, not a bare IP
> address, so this guide also sets up a free subdomain.

## Phase 1 — Create the free Oracle server

1. Sign up at <https://cloud.oracle.com> (Always Free tier; a card is required
   for identity verification, but Always Free resources are not charged).
2. **Compute → Instances → Create Instance:**
   - Image: **Ubuntu 22.04**
   - Shape: **VM.Standard.A1.Flex** (Ampere/ARM, Always Free) with
     **2 OCPU / 12 GB RAM**. If A1 capacity is unavailable, the AMD
     **VM.Standard.E2.1.Micro** works but has only 1 GB RAM.
   - **Download the SSH private key** when prompted — you need it to log in.
3. Note the instance's **public IP address**.
4. Open ports 80 and 443: in the instance's **subnet → Security List**, add two
   Ingress rules — source `0.0.0.0/0`, IP protocol TCP, destination ports
   **80** and **443**.

## Phase 2 — Get a free domain and point it at the server

1. Go to <https://duckdns.org>, sign in, and create a subdomain, e.g.
   `mystore.duckdns.org`.
2. Set its IP to your Oracle instance's **public IP** and save.
   (Or, if you own a domain, create an `A` record pointing to the public IP.)

## Phase 3 — Log in and install Docker

From your computer's terminal, or an SSH app on your phone (e.g. Termius):

```bash
ssh -i /path/to/your-key.key ubuntu@YOUR_PUBLIC_IP

# on the server:
sudo apt update && sudo apt install -y docker.io docker-compose git
sudo usermod -aG docker $USER && newgrp docker

# allow HTTP/HTTPS through the server firewall
sudo iptables -I INPUT -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT -p tcp --dport 443 -j ACCEPT
sudo netfilter-persistent save 2>/dev/null || true
```

## Phase 4 — Get OSPOS and configure it

```bash
git clone https://github.com/Kota7860/opensourcepos.git
cd opensourcepos
# use the branch that includes the mobile/PWA support (or master once merged)
git checkout claude/repo-check-b9ohzi

nano docker/.env
```

Set `docker/.env` (change the passwords!):

```
OSPOS_CI_ENV=production
OSPOS_MYSQL_USERNAME=admin
OSPOS_MYSQL_PASSWORD=CHANGE_ME_strong
OSPOS_MYSQL_ROOT_PASSWORD=CHANGE_ME_root
OSPOS_DOMAIN_NAME=mystore.duckdns.org
OSPOS_CONTACT_EMAIL=you@email.com
OSPOS_STAGING=1
```

Keep `OSPOS_STAGING=1` for the first run (test certificate — avoids Let's
Encrypt rate limits while you verify the setup).

Add your domain to the app's allowed hostnames so it will start in production:

```bash
cp .env.example .env
echo "app.allowedHostnames = 'mystore.duckdns.org'" >> .env
```

## Phase 5 — Launch (with automatic HTTPS)

```bash
bash docker/install-nginx.sh
```

This builds OSPOS and starts MariaDB, PHP, and nginx, then requests a
certificate. Once it succeeds with the staging certificate, switch to a real
one:

```bash
nano docker/.env      # set OSPOS_STAGING=0
bash docker/install-nginx.sh
```

## Phase 6 — Use it and install on mobile

1. On your phone, open `https://mystore.duckdns.org`.
2. Log in with **admin / pointofsale**, then change the password immediately.
3. Install the app:
   - **Android (Chrome):** menu (⋮) → **Install app**.
   - **iPhone/iPad (Safari):** **Share** → **Add to Home Screen**.

## Notes

- The Docker MySQL/phpMyAdmin passwords and the default `admin` password are
  well known — change them before going live.
- Certificates renew automatically; no action needed.
- To update OSPOS later: `git pull` on the branch and re-run
  `bash docker/install-nginx.sh`.
