<?php
// includes/TranslationManager.php

class TranslationManager {
    private static $instance = null;
    private $current_lang = 'en';
    private $default_lang = 'en';
    private $db;
    private $cache_dir;
    private $available_languages = [];
    private $dictionary = null; // lazy-loaded strtr() map for current language

    private function __construct($db) {
        $this->db = $db;
        $this->cache_dir = __DIR__ . '/../cache/';
        if (!is_dir($this->cache_dir)) mkdir($this->cache_dir, 0755, true);
        $this->loadAvailableLanguages();
        $this->detectLanguage();
    }

    public static function getInstance($db = null) {
        if (self::$instance === null) {
            if ($db === null) throw new Exception("DB connection required for TranslationManager");
            self::$instance = new self($db);
        }
        return self::$instance;
    }

    private function loadAvailableLanguages() {
        $result = $this->db->query("SELECT * FROM languages WHERE active = 1 ORDER BY is_default DESC, name");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $this->available_languages[$row['code']] = $row;
                if ($row['is_default']) $this->default_lang = $row['code'];
            }
        }
    }

    private function detectLanguage() {
        if (isset($_SESSION['ratin_lang']) && isset($this->available_languages[$_SESSION['ratin_lang']])) {
            $this->current_lang = $_SESSION['ratin_lang']; return;
        }
        if (isset($_COOKIE['ratin_lang']) && isset($this->available_languages[$_COOKIE['ratin_lang']])) {
            $this->current_lang = $_COOKIE['ratin_lang'];
            $_SESSION['ratin_lang'] = $this->current_lang; return;
        }
        $this->current_lang = $this->default_lang;
        $_SESSION['ratin_lang'] = $this->current_lang;
    }

    public function setLanguage($lang) {
        if (!isset($this->available_languages[$lang])) return false;
        $this->current_lang = $lang;
        $_SESSION['ratin_lang'] = $lang;
        setcookie('ratin_lang', $lang, time() + 86400 * 30, "/");
        $this->dictionary = null;
        return true;
    }

    public function getCurrentLanguage() { return $this->current_lang; }
    public function getDefaultLanguage() { return $this->default_lang; }
    public function getAvailableLanguages() { return $this->available_languages; }
    public function getLanguageMetadata($code = null) {
        $code = $code ?? $this->current_lang;
        return $this->available_languages[$code] ?? null;
    }

    /**
     * Returns [english_phrase => translated_phrase] map for current language,
     * loaded from a JSON cache file. Used by strtr() in the auto-translate pass.
     * Returns null for the default language (no replacement needed).
     */
    public function getDictionary() {
        if ($this->current_lang === $this->default_lang) return null;
        if ($this->dictionary !== null) return $this->dictionary;

        $cache_file = $this->cache_dir . 'dict_' . $this->current_lang . '.json';
        if (!file_exists($cache_file)) $this->rebuildCache($this->current_lang);
        $this->dictionary = json_decode(file_get_contents($cache_file), true) ?: [];
        return $this->dictionary;
    }

    /** Rebuild the strtr dictionary cache file for one language from approved DB rows. */
    public function rebuildCache($lang) {
        $stmt = $this->db->prepare(
            "SELECT source_text, translation FROM translations WHERE language_code = ? AND status = 'approved'"
        );
        $stmt->bind_param('s', $lang);
        $stmt->execute();
        $result = $stmt->get_result();
        $map = [];
        while ($row = $result->fetch_assoc()) {
            $map[$row['source_text']] = $row['translation'];
        }
        $stmt->close();
        file_put_contents($this->cache_dir . 'dict_' . $lang . '.json', json_encode($map, JSON_UNESCAPED_UNICODE));
    }

    public function rebuildAllCaches() {
        foreach (array_keys($this->available_languages) as $lang) $this->rebuildCache($lang);
    }

    /** Log a newly-seen English phrase that has no translation yet, for offline review. */
    public function logPendingPhrase($text) {
        $log_file = $this->cache_dir . 'pending_phrases.log';
        $fp = fopen($log_file, 'a');
        if ($fp) {
            flock($fp, LOCK_EX);
            fwrite($fp, $text . "\n");
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}