with open('revenue.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

for i, line in enumerate(lines, 1):
    if '<?php' in line or '?>' in line:
        print(f"L{i:03}: {line.strip()}")
