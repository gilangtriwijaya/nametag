#!/usr/bin/env bash

# Analyze: Jika $items buat reference ke $cfgFront['texts']
# Dan batch render memanggil renderFront berkali-kali...
# Maka modifikasi di $items akan affect renderFront panggilan berikutnya!

# Example skenario:
# 1. RenderFront(emp1) -> load $items from config -> modify items[jabatan][wrap]=1
# 2. Config sekarang memiliki wrap=1 (terpermanenkan!)
# 3. RenderFront(emp2) -> load $items from config -> items sudah memiliki wrap=1 dari sebelumnya!
# 4. Pre-scaling logic pada emp2 mungkin affected karena config sudah dimodifikasi

echo "=== CONFIG REFERENCE BUG ANALYSIS ==="
echo ""
echo "File: app/Services/NametagRenderService.php"
echo "Line 99: \$items = \$cfgFront['texts'] ?? [];"
echo ""
echo "Problem:"
echo "------"
echo "In PHP, array assignment \$items = \$cfgFront['texts'] does NOT make a copy."
echo "It makes \$items reference the SAME array."
echo ""
echo "When renderFront modifies \$items (e.g., adding __scaled_px),"
echo "it DIRECTLY MODIFIES the config array!"
echo ""
echo "This affects subsequent renderFront calls in batch:"
echo "1. First render: Config jabatan [wrap] = 2 → renderFront modifies to [wrap] = 1"
echo "2. Config now PERSISTS wrap=1"
echo "3. Second render: Loads config with wrap=1 (not original wrap=2!"
echo "4. Pre-scaling may behave differently"
echo ""
echo "Solution: Use array_merge or clone to create COPY"
echo "---"
echo "\$items = array_merge([], \$cfgFront['texts']);"
echo "OR"
echo "\$items = array_merge(\$cfgFront['texts'], []);"

echo ""
echo "This explains why batch rendering (which calls renderFront multiple times)"
echo "produces different results than single rendering!"
