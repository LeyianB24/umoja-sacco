import os, re, glob

d = 'c:/xampp/htdocs/usms/admin/pages/'
files = glob.glob(d + '*.php')
c = 0

for f in files:
    if os.path.basename(f) == 'login.php': continue
    if os.path.basename(f) == 'fix_layouts.py': continue
    with open(f, 'r', encoding='utf-8') as file:
        orig = file.read()
    content = orig

    # We want: 
    # <div class="d-flex">
    #    <?php $layout->sidebar(); ?>
    #    <div class="flex-fill main-content-wrapper p-0">
    #        <?php $layout->topbar(..); ?>
    #        <div class="container-fluid">
    
    # 1. First, rewrite the old <div class="main-wrapper"> logic to match the member panel
    # The member panel uses <div class="d-flex"> -> sidebar -> <div class="flex-fill main-content-wrapper p-0"> -> topbar
    
    # Pattern to catch:
    # <?php $layout->sidebar(); ?>
    # <div class="main-wrapper">
    #    <?php $layout->topbar(..); ?>
    #    <div class="main-content">
    
    pattern1 = re.compile(r'(<\?php\s+\$layout->sidebar\(\);\s*\?>)\s*<div\s+class="main-wrapper">\s*(<\?php\s+\$layout->topbar\([^)]*\);\s*\?>)\s*<div\s+class="main-content">', re.DOTALL)
    content = pattern1.sub(r'<div class="d-flex">\n    \1\n    <div class="flex-fill main-content-wrapper p-0">\n        \2\n        <div class="container-fluid px-4">', content)

    # 2. Also close the newly added `d-flex` at the very end after footer.
    # Pattern: <?php $layout->footer(); ?> (and optional spaces/newlines until end or <script>)
    pattern2 = re.compile(r'(<\?php\s+\$layout->footer\(\);\s*\?>)', re.IGNORECASE)
    
    # We replace layout->footer(); with laying out the closing wrappers properly
    content = pattern2.sub(r'\1\n        </div>\n    </div>\n</div>', content)
    
    # remove duplicate closures if any happen from multiple run logic
    content = re.sub(r'(</div>\s*){4,}$', '</div>\n</div>\n</div>\n', content)
    
    # 3. Clean up loose ends if they existed from the previously written logic
    if content != orig:
        with open(f, 'w', encoding='utf-8') as file:
            file.write(content)
        print(f"Refactored {os.path.basename(f)}")
        c += 1

print(f"Refactored {c} files to d-flex member logic.")
