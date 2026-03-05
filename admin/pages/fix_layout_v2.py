import os
import re

def fix_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # --- STEP 1: DESTRUCTIVE CLEANUP ---
    # Remove ALL previous layout injections (including duplicates)
    # We look for the common patterns injected before
    
    # Remove common layout wrapper divs and duplicate sidebar/topbar calls
    # This regex looks for various combinations of the sidebar/topbar/wrapper injections
    
    # 1. Strip all sidebar() and topbar() calls between header and footer
    # We'll re-inject them cleanly.
    content = re.sub(r'<\?php \$layout->sidebar\(\);\s*\?>', '', content)
    content = re.sub(r'<\?php \$layout->topbar\(.*?\);\s*\?>', '', content)
    
    # 2. Strip the wrapper divs (d-flex, main-content-wrapper, flex-fill, container-fluid)
    # We look for the specific classes we used
    content = re.sub(r'<div class="d-flex\s*[^"]*">', '', content)
    content = re.sub(r'<div class="flex-fill\s*[^"]*">', '', content)
    content = re.sub(r'<div class="main-content-wrapper\s*[^"]*">', '', content)
    content = re.sub(r'<div class="container-fluid\s*px-4\s*py-4">', '', content)
    
    # 3. Strip closing divs before the footer call
    # This is tricky because some pages might have their own divs.
    # But our script injected specific closing blocks.
    # We'll just remove all </div> calls immediately preceding the layout footer call
    content = re.sub(r'(</div>\s*)+<\?php \$layout->footer\(\);\s*\?>', '<?php $layout->footer(); ?>', content)

    # --- STEP 2: RE-APPLY CANONICAL STRUCTURE ---
    
    # 1. Header + Sidebar + Main Wrapper Start
    header_pattern = r'(<\?php \$layout->header\([^)]*\);\s*\?>)'
    replacement_start = r'\1\n<?php $layout->sidebar(); ?>\n<div class="main-content-wrapper">\n    <?php $layout->topbar($pageTitle ?? ""); ?>\n    <div class="container-fluid px-4 py-4">'
    
    if re.search(header_pattern, content):
        content = re.sub(header_pattern, replacement_start, content, count=1)
    
    # 2. Wrapper End + Footer
    footer_pattern = r'(<\?php \$layout->footer\(\);\s*\?>)'
    # We want to close the container-fluid and main-content-wrapper
    replacement_end = r'    </div> <!-- /container-fluid -->\n<?php $layout->footer(); ?>\n</div> <!-- /main-content-wrapper -->'
    
    if re.search(footer_pattern, content):
        content = re.sub(footer_pattern, replacement_end, content, count=1)

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)
    print(f"Fixed: {filepath}")

def main():
    # Admin pages directory
    admin_dir = r"c:\xampp\htdocs\usms\admin\pages"
    
    for filename in os.listdir(admin_dir):
        if filename.endswith(".php") and filename != "standardize_layout.py":
            fix_file(os.path.join(admin_dir, filename))

if __name__ == "__main__":
    main()
