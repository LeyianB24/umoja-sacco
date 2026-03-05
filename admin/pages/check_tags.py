import os

def check_tags(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
    
    open_tags = content.count('<?php')
    close_tags = content.count('?>')
    
    if open_tags != close_tags:
        print(f"ISSUE: {filepath} | O:{open_tags} C:{close_tags}")
    else:
        # Check if any HTML exists between an open and close tag (roughly)
        # Or if it's just properly balanced.
        pass

admin_dir = r"c:\xampp\htdocs\usms\admin\pages"
for filename in os.listdir(admin_dir):
    if filename.endswith(".php"):
        check_tags(os.path.join(admin_dir, filename))
