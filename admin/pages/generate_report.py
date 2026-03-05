import os

admin_dir = r"c:\xampp\htdocs\usms\admin\pages"
results = []

for filename in os.listdir(admin_dir):
    if filename.endswith(".php"):
        filepath = os.path.join(admin_dir, filename)
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
        
        open_count = content.count('<?php')
        close_count = content.count('?>')
        
        if open_count != close_count:
            results.append(f"{filename}: O={open_count}, C={close_count}")

with open(os.path.join(admin_dir, "tag_report.txt"), 'w', encoding='utf-8') as f:
    f.write("\n".join(results))

print(f"Report generated with {len(results)} issues found.")
