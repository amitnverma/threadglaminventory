# Auto-deploy to Hostinger VPS

Pushes to `main` deploy automatically to:

```
threadglam@srv792158:~/htdocs/www.threadglam.com/inventory
```

## One-time setup (3 steps)

### Step 1 — Run this on your Mac

```bash
bash scripts/setup-deploy-key.sh
```

This script will:
- Create the SSH key (if needed)
- **Copy the VPS command to your clipboard automatically**
- Optionally copy the private key for GitHub

---

### Step 2 — Paste on VPS (2 actions only)

**A)** Login to VPS:

```bash
ssh threadglam@srv792158.hstgr.cloud
```

**B)** Paste once (already in your clipboard from Step 1):

Press **Cmd+V** → **Enter**

That's it. The pasted command adds the key and creates the deploy folder.

**C)** Test from Mac (new terminal):

```bash
ssh -i ~/.ssh/threadglam_deploy threadglam@srv792158.hstgr.cloud
```

If you get a shell without a password prompt, it worked.

---

### Step 3 — Add GitHub secrets

Go to: **github.com/amitnverma/threadglaminventory → Settings → Secrets → Actions**

Add these 4 secrets:

| Secret | Value |
|--------|--------|
| `VPS_HOST` | `srv792158.hstgr.cloud` |
| `VPS_USER` | `threadglam` |
| `VPS_PATH` | `/home/threadglam/htdocs/www.threadglam.com/inventory` |
| `VPS_SSH_KEY` | Run `cat ~/.ssh/threadglam_deploy` and paste **entire** output |

Optional: `VPS_PORT` = `22`

> Tip: Run the setup script again and press `y` when asked — it copies the private key to clipboard for GitHub.

---

### Step 4 — Push to deploy

```bash
git remote add origin https://github.com/amitnverma/threadglaminventory.git
git add .
git commit -m "Initial commit with auto-deploy"
git push -u origin main
```

Check **GitHub → Actions** tab for deploy status.

Manual deploy anytime: **Actions → Deploy to Hostinger VPS → Run workflow**

---

## After first deploy — set config on server once

```bash
ssh threadglam@srv792158.hstgr.cloud
cd ~/htdocs/www.threadglam.com/inventory
cp config.example.php config.php
nano config.php   # add Hostinger MySQL credentials
```

`config.php` is never overwritten by deploys.

---

## Step 5 — Create database (required!)

**Database name:** `threadglam`

Full copy-paste guide: **[DATABASE-SETUP.md](DATABASE-SETUP.md)**

Quick version:

1. **hPanel** → Databases → create database `threadglam` + user
2. On VPS: `cp config.example.php config.php` → edit DB credentials
3. Browser: `https://www.threadglam.com/inventory/install.php` → Install
4. Delete `install.php` after success

---

## What deploy does NOT touch

- `config.php` — your DB credentials
- `uploads/` — uploaded photos

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| Permission denied | Re-run `bash scripts/setup-deploy-key.sh` and paste Step B again on VPS |
| Can't connect to VPS | Check Hostinger firewall allows port 22 |
| Site blank | Create `config.php` on server with correct DB settings |
| Images broken | `chmod 755 uploads/` on server |
