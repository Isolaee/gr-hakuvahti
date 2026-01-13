# ACF Field Analyzer

A native WordPress plugin that analyzes Advanced Custom Fields (ACF) usage directly within the WordPress admin panel.

## Features

- Analyze ACF fields without exporting data
- Real-time analysis directly in WordPress admin
- Interactive dashboard with detailed statistics
- Export results as JSON or CSV
- Track field usage across post types
- Identify missing or empty field values
- View sample values for each field
- Color-coded fill rate indicators
- Support for nested ACF fields

## Installation

### Manual Installation

1. Copy the [wp-plugin](wp-plugin/) folder to your WordPress installation's `wp-content/plugins/` directory
2. Rename the folder to `acf-analyzer` (optional but recommended)
3. Log in to WordPress admin panel
4. Navigate to **Plugins** → **Installed Plugins**
5. Find "ACF Field Analyzer" and click **Activate**

### Via ZIP Upload

1. Create a ZIP file of the [wp-plugin](wp-plugin/) folder
2. In WordPress admin, go to **Plugins** → **Add New**
3. Click **Upload Plugin**
4. Choose the ZIP file and click **Install Now**
5. Click **Activate Plugin**

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- Advanced Custom Fields (ACF) plugin installed and active

## Usage

### Running an Analysis

1. Navigate to **Tools** → **ACF Analyzer** in WordPress admin
2. Select the post types you want to analyze (osakeanti, osaketori, velkakirja, etc.)
3. Click **Run Analysis**
4. View the results on the same page

### Understanding the Results

The analysis provides:

**Overview Section:**
- Total number of posts analyzed
- Count of posts without ACF data
- Date range of analyzed posts

**Post Type Breakdown:**
- Number of posts per post type
- Percentage distribution

**ACF Field Usage:**
- Field name (including nested fields with dot notation)
- Usage count across all posts
- Fill rate (percentage of non-empty values)
- Post types using the field
- Data types found in the field
- Sample values (expandable)

**Fill Rate Color Coding:**
- Green (90-100%): High fill rate, good data quality
- Yellow (70-89%): Moderate fill rate, some missing data
- Red (0-69%): Low fill rate, significant missing data

### Exporting Results

Click one of the export buttons:
- **Export as JSON**: Download detailed results in JSON format
- **Export as CSV**: Download field statistics in CSV format for spreadsheets

## Plugin Structure

```
wp-plugin/
├── acf-analyzer.php                    # Main plugin file
├── includes/
│   ├── class-acf-analyzer.php         # Core analysis engine
│   └── class-acf-analyzer-admin.php   # Admin interface
├── templates/
│   └── admin-page.php                 # Admin dashboard
├── assets/
│   └── css/
│       └── admin.css                  # Styling
├── uninstall.php                      # Cleanup on uninstall
└── README.md                          # Plugin documentation
```

## How It Works

The plugin:
1. Queries posts in batches of 200 to avoid memory issues
2. Uses ACF's `get_fields()` function to retrieve field data
3. Recursively analyzes nested fields
4. Calculates statistics and identifies patterns
5. Stores results in WordPress transients for 1 hour
6. Displays results in a clean admin interface

## Troubleshooting

**"This plugin requires Advanced Custom Fields to be installed and activated"**
- Install and activate the ACF plugin first

**Analysis takes too long**
- Select fewer post types or the plugin will automatically process in batches

**Results disappeared**
- Results are cached for 1 hour. Run a new analysis if needed.

## License

GPL v2 or later