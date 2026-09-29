<?php
/** Cleans pasted Word-processing markup from Tour rich-text fields. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Tour_Rich_Text_Cleaner {
    /** Removes unsafe inline styles and Word-specific artifacts while retaining semantic rich-text elements. */
    /** @param mixed $chada_travel_value */
    public static function clean($chada_travel_value): string {
        $chada_travel_text = (string) $chada_travel_value;
        $chada_travel_text = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $chada_travel_text) ?? $chada_travel_text;
        $chada_travel_text = preg_replace('/<!--[\s\S]*?-->/i', '', $chada_travel_text) ?? $chada_travel_text;
        $chada_travel_text = preg_replace('/<\/?(?:o:p|w:[^>\s]+)[^>]*>/i', '', $chada_travel_text) ?? $chada_travel_text;
        $chada_travel_text = self::remove_attributes($chada_travel_text);
        if (function_exists('wp_kses_post')) {
            $chada_travel_text = wp_kses_post($chada_travel_text);
        } else {
            $chada_travel_text = strip_tags($chada_travel_text, '<p><strong><em><i><ul><ol><li><br><h1><h2><h3><h4><h5><h6><a>');
        }
        return self::remove_attributes($chada_travel_text);
    }

    private static function remove_attributes(string $chada_travel_text): string {
        $chada_travel_text = preg_replace_callback(
            '/\s+style\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i',
            static function (array $chada_travel_matches): string {
                $chada_travel_style_matches = array_slice($chada_travel_matches, 1);
                $chada_travel_style = (string) reset($chada_travel_style_matches);
                return self::safe_font_style($chada_travel_style);
            },
            $chada_travel_text
        ) ?? $chada_travel_text;
        $chada_travel_text = preg_replace(
            '/\s+data-mce-style\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i',
            '',
            $chada_travel_text
        ) ?? $chada_travel_text;
        $chada_travel_text = preg_replace_callback(
            '/\s+class\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i',
            static function (array $chada_travel_matches): string {
                $chada_travel_class_matches = array_slice($chada_travel_matches, 1);
                $chada_travel_class = (string) reset($chada_travel_class_matches);
                return preg_match('/(?:^|\s)(?:Mso[\w-]*|mso-[\w-]*)(?=\s|$)/i', $chada_travel_class)
                    ? '' : (string) $chada_travel_matches[0];
            },
            $chada_travel_text
        ) ?? $chada_travel_text;
        $chada_travel_xml_attribute_pattern = '/\s+xml(?:ns(?::[^=\s]+)?|:[^=\s]+)\s*=\s*'
            . '(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i';
        return preg_replace($chada_travel_xml_attribute_pattern, '', $chada_travel_text)
            ?? $chada_travel_text;
    }

    private static function safe_font_style(string $chada_travel_style): string {
        if (!preg_match('/(?:^|;)\s*font-family\s*:\s*([^;]+)/i', $chada_travel_style, $chada_travel_match)) {
            return '';
        }
        $chada_travel_family = strtolower(trim(str_replace(['"', "'"], '', (string) $chada_travel_match[1])));
        $chada_travel_family = preg_replace('/\s*,.*$/', '', $chada_travel_family) ?? $chada_travel_family;
        $chada_travel_allowed = [
            'andale mono' => 'Andale Mono', 'arial' => 'Arial', 'arial black' => 'Arial Black',
            'book antiqua' => 'Book Antiqua', 'calibri' => 'Calibri', 'comic sans ms' => 'Comic Sans MS',
            'courier new' => 'Courier New', 'georgia' => 'Georgia', 'helvetica' => 'Helvetica',
            'impact' => 'Impact', 'poppins' => 'Poppins', 'symbol' => 'Symbol', 'tahoma' => 'Tahoma',
            'terminal' => 'Terminal', 'times new roman' => 'Times New Roman', 'trebuchet ms' => 'Trebuchet MS',
            'verdana' => 'Verdana', 'webdings' => 'Webdings',
        ];
        return isset($chada_travel_allowed[$chada_travel_family])
            ? ' style="font-family:' . $chada_travel_allowed[$chada_travel_family] . ';"' : '';
    }
}
