<?php
/**
 * Code-owned visual tokens shared by PHP, CSS, and safe JavaScript settings.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Style_Config {
    /** @return array<string, string> */
    public static function get_tokens(): array {
        return [
            'color-background'           => '#ffffff',
            'color-foreground'           => '#111111',
            'color-surface'              => '#ffffff',
            'color-surface-muted'        => '#f5f5f5',
            'color-muted'                => '#666666',
            'color-border'               => '#999999',
            'color-border-soft'          => '#cccccc',
            'color-primary'              => '#171717',
            'color-primary-foreground'   => '#ffffff',
            'color-danger'               => '#9d1c1c',
            'color-success'              => '#176b38',
            'color-focus'                => '#4d90fe',
            'font-family'                => 'Arial, Helvetica, sans-serif',
            'container-width'            => '1180px',
            'control-radius'             => '6px',
            'space-unit'                 => '8px',
            'focus-width'                => '3px',
            'transition-duration'        => '160ms',
        ];
    }

    /** @return array<string, int> */
    public static function get_breakpoints(): array {
        return ['medium' => 900, 'small' => 680];
    }

    /** Emits the single root custom-property declaration used by registered component styles. */
    public static function get_custom_properties(): string {
        $chada_travel_declarations = [];
        foreach (self::get_tokens() as $chada_travel_token => $chada_travel_value) {
            $chada_travel_declarations[] = '--chada-travel-' . $chada_travel_token . ':' . $chada_travel_value;
        }
        return '.chada-travel-app{' . implode(';', $chada_travel_declarations) . ';}';
    }
}
