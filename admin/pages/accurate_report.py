import os

admin_dir = r"c:\xampp\htdocs\usms\admin\pages"
results = []

for filename in os.listdir(admin_dir):
    if filename.endswith(".php"):
        filepath = os.path.join(admin_dir, filename)
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
        
        o_php = content.count('<?php')
        o_short = content.count('<?=')
        c = content.count('?>')
        
        diff = (o_php + o_short) - c
        if diff != 0:
            results.append(f"{filename}: P:{o_php} S:{o_short} C:{c} DIFF:{diff}")

with open(os.path.join(admin_dir, "accurate_tag_report.txt"), 'w', encoding='utf-8') as f:
    f.write("\n".join(results))

print(f"Report generated. {len(results)} files have imbalances.")
