import os, re, glob

d = 'c:/xampp/htdocs/usms/admin/pages/'
files = glob.glob(d + '*.php')
c = 0
for f in files:
    if os.path.basename(f) == 'login.php': continue
    with open(f, 'r', encoding='utf-8') as file:
        orig = file.read()
    content = orig

    # 1. the d-flex -> main-wrapper
    pattern_top = re.compile(r'<div\s+class="d-flex">\s*(<\?php\s+\$layout->sidebar\(\);\s*\?>)\s*<div\s+class="flex-fill\s+main-content-wrapper[^>]*">\s*(<\?php\s+\$layout->topbar\([^)]*\);\s*\?>)\s*<div\s+class="container-fluid[^>]*">', re.DOTALL)
    content = pattern_top.sub(r'\1\n<div class="main-wrapper">\n    \2\n    <div class="main-content">', content)

    # 2. the trailing divs AFTER FOOTER
    pattern_bottom = re.compile(r'(<\?php\s+\$layout->footer\(\);\s*\?>)\s*(?:<\/div>\s*(?:<!--.*?-->)?\s*){1,4}', re.DOTALL)
    content = pattern_bottom.sub(r'\1\n', content)

    # 3. the manual wrapper closure inside files like users.php, dashboard.php
    # users.php: </div> <!-- Close main-content --> </div> <!-- Close main-wrapper --> right before Modals
    content = re.sub(r'</div>\s*<!-- Close main-content -->\s*</div>\s*<!-- Close main-wrapper -->', '', content)
    
    # 4. Remove leftover inline style for main-content-wrapper
    content = re.sub(r'<style>\s*\.main-content-wrapper[^\<]*</style>', '', content, flags=re.DOTALL)

    if content != orig:
        with open(f, 'w', encoding='utf-8') as file:
            file.write(content)
        print(f"Fixed {os.path.basename(f)}")
        c += 1

print(f"Fixed {c} files.")
