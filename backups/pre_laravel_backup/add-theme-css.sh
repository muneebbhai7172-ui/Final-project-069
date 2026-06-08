#!/bin/bash

# Script to add theme CSS to all admin pages
THEME_CSS_LINE='    <link rel="stylesheet" href="../../css/theme-variables.css">'

# List of module directories
MODULES=(
    "billing"
    "customers"  
    "medicines"
    "stock"
    "orders"
    "reports"
    "records"
    "backup"
    "dashboard"
)

for module in "${MODULES[@]}"; do
    file="modules/$module/index.html"
    if [ -f "$file" ]; then
        # Check if theme CSS is already included
        if ! grep -q "theme-variables.css" "$file"; then
            # Find the line with Custom CSS or similar pattern and add theme CSS before it
            sed -i '/<!-- Custom CSS -->/i\    <!-- Theme CSS -->\n    <link rel="stylesheet" href="../../css/theme-variables.css">' "$file"
            echo "Added theme CSS to $file"
        else
            echo "Theme CSS already exists in $file"
        fi
    else
        echo "File $file not found"
    fi
done

echo "Theme CSS addition completed!"