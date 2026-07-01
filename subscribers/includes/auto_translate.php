<?php
// includes/auto_translate.php
// Must be required before ANY output. Captures the full response,
// translates it generically via strtr(), and logs unknown phrases for review.

require_once __DIR__ . '/TranslationManager.php';

ob_start();

register_shutdown_function(function () {
    $content = ob_get_clean();

    try {
        // Guard 1: only ever touch actual HTML pages.
        // JSON/CSV/PDF/export endpoints fall through untouched automatically.
        $looks_like_html = (stripos($content, '<html') !== false);
        $looks_like_json = (substr(ltrim($content), 0, 1) === '{' || substr(ltrim($content), 0, 1) === '[');

        if (!$looks_like_html || $looks_like_json) {
            echo $content;
            return;
        }

        // Guard 2: skip admin/login pages (mirrors original intent — admin manages
        // translations directly, doesn't need its own UI auto-translated)
        if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
            echo $content;
            return;
        }

        $translator = TranslationManager::getInstance($GLOBALS['con'] ?? null);
        $lang = $translator->getCurrentLanguage();
        $dictionary = $translator->getDictionary(); // null if current lang === default

        // Protect <script> and <style> blocks from any text replacement
        $protected = [];
        $safe_content = preg_replace_callback(
            '/<(script|style)\b[^>]*>.*?<\/\1>/is',
            function ($m) use (&$protected) {
                $token = "\x01PROTECT" . count($protected) . "\x02";
                $protected[$token] = $m[0];
                return $token;
            },
            $content
        );

        if ($dictionary !== null && !empty($dictionary)) {
            // Single-pass replacement. strtr() with an array is O(n) over the string,
            // far cheaper than DOM parsing, and never touches HTML structure/tags.
            $safe_content = strtr($safe_content, $dictionary);
        }

        // Restore protected blocks
        foreach ($protected as $token => $original) {
            $safe_content = str_replace($token, $original, $safe_content);
        }

        // Sampled detection pass: only on ~5% of English requests, to find new
        // untranslated phrases without parsing/logging on every single hit.
        if ($lang === $translator->getDefaultLanguage() && mt_rand(1, 100) <= 5) {
            ratin_detect_new_phrases($content, $translator);
        }

        echo $safe_content;

    } catch (\Throwable $e) {
        // Never let translation machinery break the actual page.
        error_log('auto_translate failure: ' . $e->getMessage());
        echo $content;
    }
});

/**
 * Lightweight phrase extraction for detecting untranslated strings.
 * Regex-based (not DOMDocument) — cheap, sampled, and only logs to a file,
 * never writes to the DB directly (no race conditions, no live junk rows).
 */
function ratin_detect_new_phrases($html, $translator) {
    // Strip script/style first
    $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html);

    // Grab text between tags for a known set of UI-bearing elements
    preg_match_all('/<(h1|h2|h3|h4|label|button|span|p|a|legend|caption|title)\b[^>]*>([^<>]{2,200})<\/\1>/i', $html, $matches);

    $existing_dict = $translator->getDictionary() ?? [];
    $seen = [];

    foreach ($matches[2] as $text) {
        $text = trim(html_entity_decode($text));
        if ($text === '' || isset($seen[$text])) continue;
        if (!preg_match('/[A-Za-z]{3,}/', $text)) continue;       // skip numbers/symbols
        if (preg_match('/^\$?[\d.,%\s\-+]+$/', $text)) continue;  // skip prices/percentages/numbers
        if (isset($existing_dict[$text])) continue;               // already translated
        $seen[$text] = true;
        $translator->logPendingPhrase($text);
    }
}