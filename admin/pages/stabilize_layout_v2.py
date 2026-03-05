import os
import re

def fix_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # 1. Strip ALL previous layout-related tags
    content = re.sub(r'<\?php \$layout->sidebar\(\);\s*\?>', '', content)
    content = re.sub(r'<\?php \$layout->topbar\(.*?\);\s*\?>', '', content)
    content = re.sub(r'<\?php \$layout->footer\(\);\s*\?>', '', content)
    
    # Remove wrappers we might have injected
    content = re.sub(r'<div class="d-flex\s*[^"]*">', '', content)
    content = re.sub(r'<div class="flex-fill\s*[^"]*">', '', content)
    content = re.sub(r'<div class="main-content-wrapper\s*[^"]*">', '', content)
    content = re.sub(r'<div class="container-fluid\s*px-4\s*py-4">', '', content)
    
    # Remove closing tags and comments
    content = re.sub(r'</div>\s*<!--\s*/container-fluid\s*-->', '', content)
    content = re.sub(r'</div>\s*<!--\s*/main-content-wrapper\s*-->', '', content)
    content = re.sub(r'</div>\s*<!--\s*/d-flex\s*-->', '', content)
    
    # Clean up literal strings like "\n" if they were accidentally injected
    content = content.replace('\\n', '\n')

    # 2. Re-inject the Clean Canonical Structure
    header_pattern = r'(<\?php \$layout->header\([^)]*\);\s*\?>)'
    replacement_start = r'\1' + "\n" + r'<?php $layout->sidebar(); ?>' + "\n" + r'<div class="main-content-wrapper">' + "\n" + r'    <?php $layout->topbar($pageTitle ?? ""); ?>' + "\n" + r'    <div class="container-fluid px-4 py-4">'
    
    if re.search(header_pattern, content):
        content = re.sub(header_pattern, replacement_start, content, count=1)
    
    # Footer injection
    # We strip any trailing ?> and manually re-append the footer and wrapper ends
    content = content.strip()
    
    # Remove the very last ?> if it's there, we'll re-add it after our footer
    if content.endswith('?>'):
        content = content[:-2].strip()
        
    clean_footer = "\n    </div> <!-- /container-fluid -->\n    <?php $layout->footer(); ?>\n</div> <!-- /main-content-wrapper -->\n"
    
    content += clean_footer + "?>"

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)
    print(f"Standardized: {filepath}")

def main():
    admin_dir = r"c:\xampp\htdocs\usms\admin\pages"
    for filename in os.listdir(admin_dir):
        # Skip dashboard.php as we fixed it manually
        if filename.endswith(".php") and filename not in ["dashboard.php", "fix_layout_v2.py", "standardize_layout.py", "fix_layouts.py", "flex_layouts.py", "stabilize_layout.py"]:
            fix_file(os.path.join(admin_dir, filename))

if __name__ == "__main__":
    main()
