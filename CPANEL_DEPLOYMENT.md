# cPanel Manual Deployment Guide

Quick guide for deploying ACF Analyzer plugin using cPanel's "Deploy HEAD Commit" method.

## Initial Setup

### 1. Create Git Repository in cPanel

1. Login to **cPanel**
2. Navigate to **Git Version Control** (Files section)
3. Click **Create**
4. Enter details:
   ```
   Clone URL: https://github.com/Isolaee/gr-hakuvahti.git
   Repository Path: public_html/wp-content/plugins/acf-analyzer
   Repository Name: acf-analyzer
   ```
5. Click **Create**

### 2. Deploy Initial Code

1. Click **Manage** on the `acf-analyzer` repository
2. Go to **Pull or Deploy** tab
3. Click **Deploy HEAD Commit**
4. Wait for deployment to complete

### 3. Activate Plugin

1. Go to **WordPress Admin** → **Plugins**
2. Find **ACF Field Analyzer**
3. Click **Activate**
4. Navigate to **Tools** → **ACF Analyzer**

---

## Updating the Plugin

When you push changes to GitHub:

1. Go to **cPanel** → **Git Version Control**
2. Click **Manage** on `acf-analyzer`
3. Click **Pull or Deploy** tab
4. Click **Deploy HEAD Commit**
5. Done! Changes are live immediately

---

## Optional: Set Correct Permissions

If you have SSH access and want to ensure correct file permissions:

```bash
cd ~/public_html/wp-content/plugins/acf-analyzer
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
echo "Permissions set correctly"
```

---

## Quick Reference

**Deploy Command in cPanel:**
Git Version Control → Manage → Pull or Deploy → **Deploy HEAD Commit**

**File Location:**
`~/public_html/wp-content/plugins/acf-analyzer/`

**WordPress Activation:**
WordPress Admin → Plugins → Activate "ACF Field Analyzer"

**Usage:**
WordPress Admin → Tools → ACF Analyzer