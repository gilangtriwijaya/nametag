<?php
/**
 * Debug script to test the Ahli regex logic
 */

$fullText = "PENGELOLA PENGADAAN BARANG/JASA AHLI MUDA";
$line = "PENGELOLA PENGADAAN BARANG/JASA AHLI";

echo "Full Text: \"$fullText\"\n";
echo "Line (ends with Ahli): \"$line\"\n\n";

// Test 1: Does line end with "Ahli"?
if (preg_match('/\bAhli\s*$/iu', $line)) {
    echo "✓ Line ends with 'Ahli'\n\n";
    
    // Test 2: Extract "Ahli phrase" from fullText
    // The bug: This regex matches from "Ahli" to END OF FULLTEXT, not from line end
    if (preg_match('/\bAhli\s+(.+?)$/iu', $fullText, $m)) {
        $ahliPhrase = $m[0];
        echo "Extracted ahliPhrase: \"$ahliPhrase\"\n";
        
        // Test 3: Remove Ahli from line
        $lineWithoutAhli = preg_replace('/\s*Ahli\s*.*$/iu', '', $line);
        $lineWithoutAhli = trim($lineWithoutAhli);
        echo "Line without Ahli: \"$lineWithoutAhli\"\n";
        
        // Test 4: Combine
        $combined = $lineWithoutAhli . " " . $ahliPhrase;
        echo "Combined: \"$combined\"\n";
        
        // Now simulate wrapLines with 567px width
        echo "\n=== WOULD RE-WRAP THIS ===\n";
        echo "Text to wrap: \"$combined\"\n";
        echo "This should result in: \"PENGELOLA PENGADAAN BARANG/JASA AHLI MUDA\"\n";
    }
} else {
    echo "✗ Line does NOT end with 'Ahli'\n";
}

?>
