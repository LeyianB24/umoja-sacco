filepath = r"c:\xampp\htdocs\usms\admin\pages\revenue.php"
with open(filepath, 'r', encoding='utf-8') as f:
    for i, line in enumerate(f, 1):
        if '?>' in line and '<?php' not in line:
            print(f"Line {i}: {line.strip()}")
