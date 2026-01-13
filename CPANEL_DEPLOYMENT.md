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

### 3. Copy Plugin Files to Correct Location

The plugin files are in the `wp-plugin/` subdirectory. You need to copy them to the plugin root:

**Option A: Via cPanel File Manager**
1. Go to **File Manager** in cPanel
2. Navigate to: `public_html/wp-content/plugins/acf-analyzer/`
3. Select all files/folders inside `wp-plugin/`
4. Click **Copy**
5. Paste to: `public_html/wp-content/plugins/acf-analyzer/`
6. Confirm to overwrite if needed

**Option B: Via SSH** (if you have SSH access)
```bash
cd ~/public_html/wp-content/plugins/acf-analyzer
cp -R wp-plugin/* .
echo "Plugin files copied successfully"
```

### 4. Activate Plugin

1. Go to **WordPress Admin** → **Plugins**
2. Find **ACF Field Analyzer**
3. Click **Activate**
4. Navigate to **Tools** → **ACF Analyzer**

---

## Updating the Plugin

When you push changes to GitHub:

### 1. Deploy Latest Changes

1. Go to **cPanel** → **Git Version Control**
2. Click **Manage** on `acf-analyzer`
3. Click **Pull or Deploy** tab
4. Click **Deploy HEAD Commit**

### 2. Copy Updated Files (if needed)

If you modified files in `wp-plugin/` directory:
- Repeat Step 3 from Initial Setup above

---

## One-Time Setup Script

After deploying via cPanel, run this once via SSH or create a cron job:

```bash
#!/bin/bash
# File: setup-plugin-structure.sh
# Run once after initial deployment

PLUGIN_DIR=~/public_html/wp-content/plugins/acf-analyzer

cd $PLUGIN_DIR

# Copy plugin files from wp-plugin subdirectory
if [ -d "wp-plugin" ]; then
    echo "Copying plugin files..."
    cp -R wp-plugin/* .
    echo "Files copied successfully"
    echo ""
    echo "Plugin structure:"
    ls -la
else
    echo "Already in correct structure"
fi

# Set correct permissions
echo "Setting permissions..."
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

echo "Setup complete!"
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