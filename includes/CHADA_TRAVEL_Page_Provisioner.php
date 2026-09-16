<?php
/**
 * Creates a WordPress Page for each of the seven Pages & Links / Policies & Consent slots (see CHADA_TRAVEL_Page_Registry,
 * CHADA_TRAVEL_Policy_Registry) whenever no real page currently satisfies it. Called once from
 * CHADA_TRAVEL_Installer::activate(), itself safe to re-run on every activation and upgrade, so this is too: re-running
 * finds nothing left to provision once every slot resolves to a real page.
 *
 * Only ever provisions a slot that is genuinely, never-before-configured - id still 0 (workflow pages) or id 0
 * with no External URL (policies). A Draft/Private page an administrator is still preparing, a
 * deliberately external-URL-only policy configuration, AND a stale nonzero id that no longer resolves to a real
 * page (Missing status - e.g. an administrator-assigned page was later trashed) are all left completely alone:
 * a nonzero id is administrator-edited data, and this codebase's existing contract is that such data survives an
 * upgrade verbatim (see tests/integration/installer-runtime.php) - the pre-existing Missing-status
 * admin notice remains the only prompt to fix it, exactly as before this feature existed. When a page already
 * exists at the target slug (created by hand before this feature existed, or left over from an earlier
 * provisioning run whose option write failed) its id is adopted rather than a duplicate being created.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Page_Provisioner {
    /** Every selectable-but-not-Trash post status a Page can meaningfully already exist in. */
    private const SELECTABLE_STATUSES = ['publish', 'draft', 'pending', 'private', 'future'];

    public static function provision_missing_pages(): void {
        if (!function_exists('wp_insert_post')) {
            return;
        }
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();

        $chada_travel_page_labels = CHADA_TRAVEL_Page_Registry::get_labels();
        foreach (CHADA_TRAVEL_Page_Registry::get_definitions() as $chada_travel_key => $chada_travel_definition) {
            // Only a genuinely never-configured slot (id still 0) is provisioned. A nonzero id that no longer
            // resolves (STATUS_MISSING - e.g. an administrator-assigned page was later trashed) is deliberately
            // left alone: that id is administrator-edited data, and this codebase's existing contract is that
            // such data survives an upgrade verbatim (see tests/integration/installer-runtime.php) -
            // the pre-existing Missing-status admin notice remains the only prompt to fix it, exactly as before
            // this feature existed.
            if (CHADA_TRAVEL_Page_Settings::resolve_status($chada_travel_key, $chada_travel_settings)['status']
                !== CHADA_TRAVEL_Page_Settings::STATUS_NOT_SELECTED
            ) {
                continue;
            }
            self::provision_one(
                $chada_travel_definition['option'],
                $chada_travel_definition['slug'],
                $chada_travel_page_labels[$chada_travel_key] ?? $chada_travel_definition['slug'],
                '<!-- wp:shortcode -->[' . $chada_travel_definition['shortcode'] . ']<!-- /wp:shortcode -->'
            );
        }

        $chada_travel_policy_labels = CHADA_TRAVEL_Policy_Registry::get_labels();
        foreach (CHADA_TRAVEL_Policy_Registry::get_definitions() as $chada_travel_key => $chada_travel_definition) {
            // Same reasoning as the workflow-page loop above: only a genuinely never-configured policy (page id
            // still 0 and no External URL) is provisioned. A nonzero page id that no longer resolves
            // (STATUS_MISSING_NO_FALLBACK) is administrator-edited data and is deliberately left exactly as-is.
            if (CHADA_TRAVEL_Policy_Settings::resolve_policy_status($chada_travel_key, $chada_travel_settings)['status']
                !== CHADA_TRAVEL_Policy_Settings::STATUS_NOT_CONFIGURED
            ) {
                continue;
            }
            self::provision_one(
                $chada_travel_definition['page_option'],
                $chada_travel_definition['slug'],
                $chada_travel_policy_labels[$chada_travel_key] ?? $chada_travel_definition['slug'],
                self::policy_content($chada_travel_key, $chada_travel_settings)
            );
        }
    }

    /** Provisions the Tour Search Results Page when other workflow pages are not yet configured. */
    public static function provision_tour_search_results_page(): void {
        if (!function_exists('wp_insert_post')) {
            return;
        }
        $chada_travel_definition = CHADA_TRAVEL_Page_Registry::get_definitions()[CHADA_TRAVEL_Page_Registry::TOUR_SEARCH_RESULTS] ?? null;
        if (!$chada_travel_definition || CHADA_TRAVEL_Page_Settings::resolve_status(
            CHADA_TRAVEL_Page_Registry::TOUR_SEARCH_RESULTS,
            CHADA_TRAVEL_Config::get_settings()
        )['status'] !== CHADA_TRAVEL_Page_Settings::STATUS_NOT_SELECTED) {
            return;
        }
        $chada_travel_labels = function_exists('__') ? CHADA_TRAVEL_Page_Registry::get_labels() : [];
        self::provision_one(
            $chada_travel_definition['option'],
            $chada_travel_definition['slug'],
            $chada_travel_labels[CHADA_TRAVEL_Page_Registry::TOUR_SEARCH_RESULTS] ?? 'Tour Search Results',
            '<!-- wp:shortcode -->[' . $chada_travel_definition['shortcode'] . ']<!-- /wp:shortcode -->'
        );
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function policy_content(string $chada_travel_key, array $chada_travel_settings): string {
        switch ($chada_travel_key) {
            case CHADA_TRAVEL_Policy_Registry::PRIVACY:
                return CHADA_TRAVEL_Standard_Page_Content::privacy_policy_content($chada_travel_settings);
            case CHADA_TRAVEL_Policy_Registry::TERMS:
                return CHADA_TRAVEL_Standard_Page_Content::terms_conditions_content($chada_travel_settings);
            case CHADA_TRAVEL_Policy_Registry::CANCELLATION_REFUND:
                return CHADA_TRAVEL_Standard_Page_Content::cancellation_refund_content($chada_travel_settings);
            default:
                return '';
        }
    }

    /** Adopts an existing page at $chada_travel_slug if one exists, otherwise creates it; always safe to re-run. */
    private static function provision_one(
        string $chada_travel_option,
        string $chada_travel_slug,
        string $chada_travel_title,
        string $chada_travel_content
    ): void {
        self::provision_one_result($chada_travel_option, $chada_travel_slug, $chada_travel_title, $chada_travel_content, 'publish');
    }

    /**
     * Explicit, wizard-triggered equivalent of provision_one() for one workflow-page slot (Checkout/Payment
     * Proof/Document Upload): adopts an existing same-slug page untouched, otherwise creates it Published -
     * matching provision_missing_pages()'s existing workflow-page default - and returns a specific, actionable
     * result instead of a silent void. Never partially writes the option on failure.
     *
     * @return array{success: bool, page_id: int, error: string, adopted: bool}
     */
    public static function create_recommended_workflow_page(string $chada_travel_key): array {
        $chada_travel_definitions = CHADA_TRAVEL_Page_Registry::get_definitions();
        if (!isset($chada_travel_definitions[$chada_travel_key])) {
            return ['success' => false, 'page_id' => 0, 'error' => __('Unknown page.', 'chada-travel'), 'adopted' => false];
        }
        $chada_travel_definition = $chada_travel_definitions[$chada_travel_key];
        $chada_travel_labels     = CHADA_TRAVEL_Page_Registry::get_labels();
        return self::provision_one_result(
            $chada_travel_definition['option'],
            $chada_travel_definition['slug'],
            $chada_travel_labels[$chada_travel_key] ?? $chada_travel_definition['slug'],
            '<!-- wp:shortcode -->[' . $chada_travel_definition['shortcode'] . ']<!-- /wp:shortcode -->',
            'publish'
        );
    }

    /**
     * Explicit, wizard-triggered equivalent of provision_one() for one policy slot (Privacy/Terms/Cancellation
     * and Refund): adopts an existing same-slug page untouched (its current status is never changed), otherwise
     * creates a brand-new page as Draft - so the owner reviews and publishes the generated starter legal text
     * (see CHADA_TRAVEL_Standard_Page_Content) before it goes live, unlike provision_missing_pages()'s own Published
     * default for the automatic-provisioning-on-upgrade path. Returns a specific, actionable result.
     *
     * @param array<string, mixed> $chada_travel_settings CHADA_TRAVEL_Config::get_settings() result.
     * @return array{success: bool, page_id: int, error: string, adopted: bool}
     */
    public static function create_recommended_policy_page(string $chada_travel_key, array $chada_travel_settings): array {
        $chada_travel_definitions = CHADA_TRAVEL_Policy_Registry::get_definitions();
        if (!isset($chada_travel_definitions[$chada_travel_key])) {
            return [
                'success' => false, 'page_id' => 0, 'error' => __('Unknown policy.', 'chada-travel'), 'adopted' => false,
            ];
        }
        $chada_travel_definition = $chada_travel_definitions[$chada_travel_key];
        $chada_travel_labels     = CHADA_TRAVEL_Policy_Registry::get_labels();
        return self::provision_one_result(
            $chada_travel_definition['page_option'],
            $chada_travel_definition['slug'],
            $chada_travel_labels[$chada_travel_key] ?? $chada_travel_definition['slug'],
            self::policy_content($chada_travel_key, $chada_travel_settings),
            'draft'
        );
    }

    /**
     * Appends the required Gutenberg shortcode block to an existing workflow page's content without touching
     * any existing block - never replaces, reorders, or removes current content. A page that already contains
     * the shortcode is left completely unchanged (idempotent repeat action). Returns a specific, actionable
     * error and leaves the page content unchanged whenever the page cannot be found or the update fails.
     *
     * @return array{success: bool, error: string, already_present: bool}
     */
    public static function add_workflow_shortcode(string $chada_travel_key, int $chada_travel_page_id): array {
        $chada_travel_definitions = CHADA_TRAVEL_Page_Registry::get_definitions();
        if (!isset($chada_travel_definitions[$chada_travel_key])) {
            return ['success' => false, 'error' => __('Unknown page.', 'chada-travel'), 'already_present' => false];
        }
        return self::append_shortcode_to_page($chada_travel_page_id, $chada_travel_definitions[$chada_travel_key]['shortcode']);
    }

    /**
     * Shared adopt-or-create implementation behind provision_one() (silent, unconditional, always Published) and
     * the explicit wizard actions above (specific result, caller-chosen status). Adoption never touches an
     * existing page's status or content - only a brand-new page is created with $chada_travel_status.
     *
     * @return array{success: bool, page_id: int, error: string, adopted: bool}
     */
    private static function provision_one_result(
        string $chada_travel_option,
        string $chada_travel_slug,
        string $chada_travel_title,
        string $chada_travel_content,
        string $chada_travel_status
    ): array {
        if (!function_exists('wp_insert_post')) {
            return [
                'success' => false, 'page_id' => 0,
                'error'   => __('This action requires a real WordPress environment.', 'chada-travel'), 'adopted' => false,
            ];
        }
        $chada_travel_existing_id = self::find_page_by_slug($chada_travel_slug);
        if ($chada_travel_existing_id > 0) {
            update_option($chada_travel_option, $chada_travel_existing_id);
            return ['success' => true, 'page_id' => $chada_travel_existing_id, 'error' => '', 'adopted' => true];
        }

        $chada_travel_new_id = wp_insert_post([
            'post_type'    => 'page',
            'post_status'  => $chada_travel_status,
            'post_title'   => $chada_travel_title,
            'post_name'    => $chada_travel_slug,
            'post_content' => $chada_travel_content,
        ], true);
        if (is_wp_error($chada_travel_new_id)) {
            return [
                'success' => false, 'page_id' => 0, 'error' => $chada_travel_new_id->get_error_message(), 'adopted' => false,
            ];
        }
        update_option($chada_travel_option, $chada_travel_new_id);
        return ['success' => true, 'page_id' => $chada_travel_new_id, 'error' => '', 'adopted' => false];
    }

    /**
     * @return array{success: bool, error: string, already_present: bool}
     */
    private static function append_shortcode_to_page(int $chada_travel_page_id, string $chada_travel_shortcode): array {
        if (!function_exists('get_post') || !function_exists('wp_update_post')) {
            return [
                'success' => false,
                'error'   => __('This action requires a real WordPress environment.', 'chada-travel'),
                'already_present' => false,
            ];
        }
        $chada_travel_post = get_post($chada_travel_page_id);
        if (!$chada_travel_post || $chada_travel_post->post_type !== 'page' || $chada_travel_post->post_status === 'trash') {
            return [
                'success' => false, 'error' => __('Select an existing WordPress Page.', 'chada-travel'),
                'already_present' => false,
            ];
        }
        $chada_travel_content = (string) $chada_travel_post->post_content;
        if (function_exists('has_shortcode') && has_shortcode($chada_travel_content, $chada_travel_shortcode)) {
            return ['success' => true, 'error' => '', 'already_present' => true];
        }
        $chada_travel_new_content = rtrim($chada_travel_content) . "\n\n"
            . '<!-- wp:shortcode -->[' . $chada_travel_shortcode . ']<!-- /wp:shortcode -->';
        $chada_travel_updated = wp_update_post(['ID' => $chada_travel_page_id, 'post_content' => $chada_travel_new_content], true);
        if (is_wp_error($chada_travel_updated)) {
            return ['success' => false, 'error' => $chada_travel_updated->get_error_message(), 'already_present' => false];
        }
        return ['success' => true, 'error' => '', 'already_present' => false];
    }

    /**
     * Finds a real Page at $chada_travel_slug across every post status a Page can meaningfully already be in - never
     * Trash, so a previously deleted same-slug page is never mistaken for a live one (WordPress also mangles a
     * trashed post's post_name with a "-__trashed" suffix, so it would not match $chada_travel_slug regardless).
     */
    private static function find_page_by_slug(string $chada_travel_slug): int {
        if (!function_exists('get_posts')) {
            return 0;
        }
        $chada_travel_matches = get_posts([
            'name'          => $chada_travel_slug,
            'post_type'     => 'page',
            'post_status'   => self::SELECTABLE_STATUSES,
            'numberposts'   => 1,
            'fields'        => 'ids',
            'no_found_rows' => true,
        ]);
        return !empty($chada_travel_matches) ? (int) $chada_travel_matches[0] : 0;
    }
}
