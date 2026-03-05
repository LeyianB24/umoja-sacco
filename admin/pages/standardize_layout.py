import os
import re
import glob

admin_pages_dir = 'c:/xampp/htdocs/usms/admin/pages/'
php_files = glob.glob(os.path.join(admin_pages_dir, '*.php'))

def refactor_page(content):
    # 1. Ensure layout helper call is correct
    # From: <?php $layout->header($pageTitle); ?>
    # To canonical start: 
    # <?php $layout->header($pageTitle); ?>
    # <div class="d-flex">
    #     <?php $layout->sidebar(); ?>
    #     <div class="flex-fill main-content-wrapper">
    #         <?php $layout->topbar($pageTitle); ?>
    #         <div class="container-fluid px-4 py-4">

    # First, let's remove any existing fragmented layout structures we added in previous attempts
    content = re.sub(r'<div class="d-flex">\s*<\?php \$layout->sidebar\(\);\s*\?>\s*<div class="flex-fill main-content-wrapper p-0">\s*<\?php \$layout->topbar\([^)]*\);\s*\?>\s*<div class="container-fluid[^>]*>', '', content, flags=re.DOTALL)
    
    # Also remove any lone main-wrapper or main-content starting tags if they survived
    content = re.sub(r'<div class="main-wrapper">\s*<\?php \$layout->topbar\([^)]*\);\s*\?>\s*<div class="main-content">', '', content, flags=re.DOTALL)
    
    # 2. Re-inject the clean structure after the header call
    header_pattern = r'(<\?php \$layout->header\([^)]*\);\s*\?>)'
    replacement_start = r'\1\n<?php $layout->sidebar(); ?>\n<div class="main-content-wrapper">\n    <?php $layout->topbar($pageTitle ?? ""); ?>\n    <div class="container-fluid px-4 py-4">'
    
    if re.search(header_pattern, content):
        content = re.sub(header_pattern, replacement_start, content)
    
    # 3. Handle the footer and closing tags
    # We want: 
    #         </div> <!-- /container-fluid -->
    #         <?php $layout->footer(); ?>
    #     </div> <!-- /main-content-wrapper -->

    # Clean up any existing closing fragments
    content = re.sub(r'<\?php \$layout->footer\(\);\s*\?>\s*(</div>\s*){1,4}', '', content, flags=re.DOTALL)
    
    # Replace the footer call with the structured version
    footer_pattern = r'<\?php \$layout->footer\(\);\s*\?>'
    replacement_end = r'        </div>\n        <?php $layout->footer(); ?>\n    </div>'
    
    content = re.sub(footer_pattern, replacement_end, content)

    return content

for file_path in php_files:
    if os.path.basename(file_path) == 'login.php':
        continue
        
    print(f"Refactoring {file_path}...")
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    new_content = refactor_page(content)
    
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(new_content)

print("Refactor complete.")
