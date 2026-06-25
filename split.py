import re
import os

with open('indexwatch-app.html', 'r', encoding='utf-8') as f:
    content = f.read()

css_match = re.search(r'<style>(.*?)</style>', content, re.DOTALL)
js_match = re.search(r'<script>(.*?)</script>', content, re.DOTALL)

css = css_match.group(1).strip() if css_match else ''
js = js_match.group(1).strip() if js_match else ''

html = content
if css_match:
    html = html.replace(css_match.group(0), '<link rel="stylesheet" href="{{ asset(\'css/dashboard.css\') }}">')
if js_match:
    html = html.replace(js_match.group(0), '<script src="{{ asset(\'js/dashboard.js\') }}"></script>')

os.makedirs('public/css', exist_ok=True)
os.makedirs('public/js', exist_ok=True)
os.makedirs('resources/views', exist_ok=True)

with open('public/css/dashboard.css', 'w', encoding='utf-8') as f:
    f.write(css)
with open('public/js/dashboard.js', 'w', encoding='utf-8') as f:
    f.write(js)
with open('resources/views/dashboard.blade.php', 'w', encoding='utf-8') as f:
    f.write(html)

print('Split complete.')
