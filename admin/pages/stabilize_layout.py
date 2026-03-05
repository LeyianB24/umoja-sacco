import os
import re

def fix_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # 1. Strip ALL previous layout-related tags to reach a "clean" baseline
    # We remove sidebars, topbars, and common wrapper divs
    content = re.sub(r'<\?php \$layout->sidebar\(\);\s*\?>', '', content)
    content = re.sub(r'<\?php \$layout->topbar\(.*?\);\s*\?>', '', content)
    content = re.sub(r'<\?php \$layout->footer\(\);\s*\?>', '', content)
    
    content = re.sub(r'<div class="d-flex\s*[^"]*">', '', content)
    content = re.sub(r'<div class="flex-fill\s*[^"]*">', '', content)
    content = re.sub(r'<div class="main-content-wrapper\s*[^"]*">', '', content)
    content = re.sub(r'<div class="container-fluid\s*px-4\s*py-4">', '', content)
    
    # Remove closing comments and trailing divs we might have injected
    content = re.sub(r'</div>\s*<!--\s*/container-fluid\s*-->', '', content)
    content = re.sub(r'</div>\s*<!--\s*/main-content-wrapper\s*-->', '', content)
    content = re.sub(r'</div>\s*<!--\s*/d-flex\s*-->', '', content)

    # 2. Re-inject the Clean Canonical Structure
    # After Header: sidebar + wrapper start + topbar + container start
    header_pattern = r'(<\?php \$layout->header\([^)]*\);\s*\?>)'
    replacement_start = r'\1\n<?php $layout->sidebar(); ?>\n<div class="main-content-wrapper">\n    <?php $layout->topbar($pageTitle ?? ""); ?>\n    <div class="container-fluid px-4 py-4">'
    
    if re.search(header_pattern, content):
        content = re.sub(header_pattern, replacement_start, content, count=1)
    
    # Before the end of PHP or specific markers, inject the Footer + wrapper end
    # We'll look for the end of the content before script tags or the end of file
    
    # Find a good place for the footer. Usually at the end of the file.
    # If the file ends with ?> we put it before that.
    
    clean_footer = r'\n    </div> <!-- /container-fluid -->\n    <?php $layout->footer(); ?>\n</div> <!-- /main-content-wrapper -->\n'
    
    if '?>' in content:
        # Avoid double inject if we run multiple times
        if '$layout->footer()' not in content:
            # Replace the LAST ?> or append if it's a common closing pattern
            # Actually, let's just append it before the very last ?> if it's likely the end of the page
            parts = content.rsplit('?>', 1)
            if len(parts) > 1:
                content = parts[0] + clean_footer + '?>' + parts[1]
            else:
                content += clean_footer
    else:
        content += clean_footer

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)
    print(f"Standardized: {filepath}")

def main():
    admin_dir = r"c:\xampp\htdocs\usms\admin\pages"
    for filename in os.listdir(admin_dir):
        if filename.endswith(".php") and filename not in ["fix_layout_v2.py", "standardize_layout.py", "fix_layouts.py", "flex_layouts.py"]:
            fix_file(os.path.join(admin_dir, filename))

if __name__ == "__main__":
    main()
