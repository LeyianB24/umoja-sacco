import re

with open('revenue.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Find all occurrences and print their context
for match in re.finditer(r'<?php|\?>', content):
    start = max(0, match.start() - 20)
    end = min(len(content), match.end() + 20)
    context = content[start:end].replace('\n', '\\n')
    print(f"Pos {match.start()} | Type {match.group()} | Context: ...{context}...")
