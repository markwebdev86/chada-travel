<?php
/**
 * Stage 1-3 public checkout shortcode built from the shared reusable view components.
 *
 * Stage 1 country panels, Stage 2 applicant groups, and Stage 3 review lists are populated by
 * chada-travel-checkout.js from the same server-authoritative data returned by CHADA_TRAVEL_Rest_Controller; PHP still owns
 * every field, fee, and total.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Checkout {
    public const SHORTCODE_TAG = 'chada_travel_visa_application';

    public static function register(): void {
        add_shortcode(self::SHORTCODE_TAG, [self::class, 'render']);
    }

    /** @param array<string, mixed>|string $chada_travel_atts Unused; the checkout has no shortcode attributes. */
    public static function render($chada_travel_atts = []): string {
        CHADA_TRAVEL_View_Components::send_customer_security_headers();
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();

        if (!CHADA_TRAVEL_Config::is_accepting_new_applications($chada_travel_settings)) {
            return self::render_maintenance($chada_travel_settings);
        }
        if (!CHADA_TRAVEL_Policy_Settings::bundle_status($chada_travel_settings)['ready']) {
            // A brand-new visit can never start a checkout while the policy bundle is not Ready; the REST layer
            // independently rejects a forged request to create a draft (see
            // CHADA_TRAVEL_Rest_Controller::guard_policy_bundle_ready()), so this notice is never the only enforcement.
            // A resuming Booker with an already-created order keeps their own immutable snapshot regardless of
            // this page-load-time check - see CHADA_TRAVEL_Order_Repository::update_booker()'s snapshot handling.
            return self::render_policy_unavailable($chada_travel_settings);
        }

        $chada_travel_stages = CHADA_TRAVEL_Workflow::get_checkout_stages();

        return CHADA_TRAVEL_Template::render('frontend/checkout/shell', [
            'chada_travel_brand_header_html' => CHADA_TRAVEL_View_Components::company_brand_header($chada_travel_settings),
            'chada_travel_progress_html'     => CHADA_TRAVEL_View_Components::progress(1, [1]),
            'chada_travel_stage_one_html'    => self::render_stage(
                1,
                $chada_travel_stages[1]['label'],
                self::render_stage_one($chada_travel_settings),
                true
            ),
            'chada_travel_stage_two_html'    => self::render_stage(
                2,
                $chada_travel_stages[2]['label'],
                self::render_stage_two($chada_travel_settings)
            ),
            'chada_travel_stage_three_html'  => self::render_stage(
                3,
                $chada_travel_stages[3]['label'],
                self::render_stage_three($chada_travel_settings),
                false,
                true
            ),
            'chada_travel_stage_four_html'   => self::render_stage(4, $chada_travel_stages[4]['label'], self::render_stage_four()),
            'chada_travel_stage_five_html'   => self::render_stage(
                5,
                $chada_travel_stages[5]['label'],
                self::render_stage_five($chada_travel_settings)
            ),
            'chada_travel_confirmation_dialog_html' => self::render_confirmation_dialog(),
            'chada_travel_noscript_notice_html'      => CHADA_TRAVEL_View_Components::notice(
                'Enable JavaScript to complete the Visa Application checkout.',
                'error'
            ),
            'chada_travel_footer_html'       => self::render_footer($chada_travel_settings),
        ]);
    }

    /**
     * Renders a branded, accessible maintenance notice instead of the new-customer checkout workflow when
     * Company Status is "Under Maintenance". This is the customer-facing half of the maintenance workflow; the
     * REST layer independently rejects a forged request to start a new checkout draft (see
     * CHADA_TRAVEL_Rest_Controller::handle_booker()), so this notice is never the only enforcement.
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    private static function render_maintenance(array $chada_travel_settings): string {
        $chada_travel_message = (string) $chada_travel_settings['chada_travel_company_maintenance_message'];
        return CHADA_TRAVEL_Template::render('frontend/checkout/maintenance', [
            'chada_travel_brand_header_html' => CHADA_TRAVEL_View_Components::company_brand_header($chada_travel_settings),
            'chada_travel_notice_html'       => CHADA_TRAVEL_View_Components::notice($chada_travel_message, 'info'),
            'chada_travel_footer_html'       => self::render_footer($chada_travel_settings),
        ]);
    }

    /**
     * Renders a branded, customer-safe notice instead of the checkout workflow when the current policy bundle
     * (Policy Version, Effective Date, Privacy, Terms, and Cancellation/Refund sources) is not Ready. This is
     * a technical-consent-record readiness gate, not a legal-compliance statement.
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    private static function render_policy_unavailable(array $chada_travel_settings): string {
        $chada_travel_message = 'New visa applications are temporarily unavailable while required policy information '
            . 'is being finalized. Please check back soon.';
        return CHADA_TRAVEL_Template::render('frontend/checkout/policy-unavailable', [
            'chada_travel_brand_header_html' => CHADA_TRAVEL_View_Components::company_brand_header($chada_travel_settings),
            'chada_travel_notice_html'       => CHADA_TRAVEL_View_Components::notice($chada_travel_message, 'info'),
            'chada_travel_support_email'     => (string) $chada_travel_settings['chada_travel_company_support_email'],
            'chada_travel_footer_html'       => self::render_footer($chada_travel_settings),
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function render_footer(array $chada_travel_settings): string {
        $chada_travel_contact = CHADA_TRAVEL_View_Components::company_contact_block($chada_travel_settings);
        return $chada_travel_contact !== '' ? '<footer class="chada-travel-app-footer">' . $chada_travel_contact . '</footer>' : '';
    }

    /** The wireframe hides only the Stage 3 heading visually; Stages 1, 2, and 4 keep a visible heading. */
    private static function render_stage(
        int $chada_travel_stage,
        string $chada_travel_label,
        string $chada_travel_body,
        bool $chada_travel_visible = false,
        bool $chada_travel_heading_visually_hidden = false
    ): string {
        return CHADA_TRAVEL_Template::render('frontend/checkout/stage-shell', [
            'chada_travel_stage'                  => $chada_travel_stage,
            'chada_travel_hidden_attr_html'       => $chada_travel_visible ? '' : ' hidden',
            'chada_travel_heading_class_attr_html' => $chada_travel_heading_visually_hidden ? ' class="chada-travel-sr-only"' : '',
            'chada_travel_heading_html'           => 'Stage ' . $chada_travel_stage . ': '
                . CHADA_TRAVEL_View_Components::escape_html($chada_travel_label),
            'chada_travel_body_html'              => $chada_travel_body,
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function render_stage_one(array $chada_travel_settings): string {
        unset($chada_travel_settings);
        return CHADA_TRAVEL_Template::render('frontend/checkout/stage-one', [
            'chada_travel_cart_status_html' => self::render_cart_status(),
            'chada_travel_actions_html'     => CHADA_TRAVEL_View_Components::actions([
                ['label' => 'Cancel Application', 'hidden' => true, 'data' => ['chada-travel-cancel-application' => '']],
                ['label' => 'Next: Booker and Applicants →', 'variant' => 'primary',
                    'data' => ['chada-travel-stage-one-next' => '']],
            ]),
        ]);
    }

    /**
     * Renders the Stage 2 combined Privacy/Terms consent checkbox. Only ever called once render() has already
     * confirmed the policy bundle is Ready, so both links always resolve to a real URL here.
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    private static function consent_row(array $chada_travel_settings): string {
        $chada_travel_bundle = CHADA_TRAVEL_Policy_Settings::resolve_effective_bundle($chada_travel_settings);
        $chada_travel_privacy_link = self::policy_link(
            (string) ($chada_travel_bundle['privacy']['url'] ?? ''),
            'Privacy Policy',
            'privacy'
        );
        $chada_travel_terms_link = self::policy_link(
            (string) ($chada_travel_bundle['terms']['url'] ?? ''),
            'Terms and Conditions',
            'terms'
        );
        $chada_travel_row = CHADA_TRAVEL_View_Components::check_row(
            'privacy_terms_consent',
            'I agree to the ' . $chada_travel_privacy_link . ' and ' . $chada_travel_terms_link . '.',
            'chada-travel-consent'
        );
        return $chada_travel_row . self::policy_caption($chada_travel_settings);
    }

    /**
     * Renders one policy name as a link opening in a new tab (never losing in-progress form values), or as
     * plain text when no resolved URL is available yet. $chada_travel_key tags the anchor so chada-travel-checkout.js can
     * refresh its href after a draft/snapshot is resolved (see CHADA_TRAVEL_Rest_Controller::browser_safe_policy()),
     * so a link never silently keeps pointing at a newer Settings policy bundle than the one actually accepted.
     */
    private static function policy_link(string $chada_travel_url, string $chada_travel_label, string $chada_travel_key): string {
        if ($chada_travel_url === '') {
            return CHADA_TRAVEL_View_Components::escape_html($chada_travel_label);
        }
        return '<a href="' . CHADA_TRAVEL_View_Components::escape_attr($chada_travel_url) . '" target="_blank" '
            . 'rel="noopener noreferrer" data-chada-travel-policy-link="' . CHADA_TRAVEL_View_Components::escape_attr($chada_travel_key)
            . '">' . CHADA_TRAVEL_View_Components::escape_html($chada_travel_label)
            . '<span class="chada-travel-sr-only"> (opens in a new tab)</span></a>';
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function policy_caption(array $chada_travel_settings): string {
        $chada_travel_version        = (string) $chada_travel_settings['chada_travel_policy_version'];
        $chada_travel_effective_date = (string) $chada_travel_settings['chada_travel_policy_effective_date'];
        return '<p class="chada-travel-policy-caption" data-chada-travel-policy-caption>Policy Version '
            . CHADA_TRAVEL_View_Components::escape_html($chada_travel_version) . ' &middot; Effective '
            . CHADA_TRAVEL_View_Components::escape_html($chada_travel_effective_date) . '</p>';
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function render_stage_two(array $chada_travel_settings): string {
        $chada_travel_fields = CHADA_TRAVEL_View_Components::form_grid([
            CHADA_TRAVEL_View_Components::control(['label' => 'First Name', 'name' => 'first_name', 'required' => true]),
            CHADA_TRAVEL_View_Components::control(['label' => 'Last Name (Optional)', 'name' => 'last_name']),
            CHADA_TRAVEL_View_Components::control([
                'label' => 'Email Address', 'name' => 'email', 'type' => 'email', 'required' => true,
            ]),
            CHADA_TRAVEL_View_Components::control(['label' => 'Mobile Number', 'name' => 'mobile', 'required' => true]),
            CHADA_TRAVEL_View_Components::control([
                'label' => 'Full Address (Optional)', 'name' => 'address', 'type' => 'textarea', 'full' => true,
            ]),
        ]) . self::consent_row($chada_travel_settings);
        $chada_travel_main = CHADA_TRAVEL_View_Components::panel('Booker Details', $chada_travel_fields, 'chada-travel-booker-panel');
        $chada_travel_aside = CHADA_TRAVEL_View_Components::panel(
            'Order Summary',
            '<div class="chada-travel-summary-list" id="chada-travel-stage-2-summary"></div>'
        );
        $chada_travel_actions = CHADA_TRAVEL_View_Components::actions([
            ['label' => '← Back: Visa Countries', 'data' => ['chada-travel-stage-target' => 1]],
            ['label' => 'Cancel Application', 'hidden' => true, 'data' => ['chada-travel-cancel-application' => '']],
            ['label' => 'Next: Review and Edit →', 'variant' => 'primary', 'type' => 'submit'],
        ]);
        return CHADA_TRAVEL_Template::render('frontend/checkout/stage-two', [
            'chada_travel_cart_status_html' => self::render_cart_status(),
            'chada_travel_main_aside_html'  => CHADA_TRAVEL_View_Components::main_aside($chada_travel_main, $chada_travel_aside),
            'chada_travel_actions_html'     => $chada_travel_actions,
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function render_stage_three(array $chada_travel_settings): string {
        $chada_travel_booker_panel = CHADA_TRAVEL_View_Components::panel(
            'Booker Information',
            '<div class="chada-travel-review-list" id="chada-travel-review-booker"></div>',
            '',
            '<button class="chada-travel-button chada-travel-button--small" type="button" data-chada-travel-stage-target="2">'
                . 'Edit Booker Details</button>'
        );
        $chada_travel_applications_panel = CHADA_TRAVEL_View_Components::panel(
            'Visa Applications',
            CHADA_TRAVEL_View_Components::table(
                ['Country', 'Applicant', 'Target Travel Date', 'Processing Fee', 'Actions'],
                [],
                'chada-travel-review-applications'
            ),
            'chada-travel-panel--spaced'
        );
        $chada_travel_aside = CHADA_TRAVEL_View_Components::panel(
            'Order Total',
            '<div class="chada-travel-summary-list" id="chada-travel-stage-3-summary"></div>'
        );
        $chada_travel_bundle = CHADA_TRAVEL_Policy_Settings::resolve_effective_bundle($chada_travel_settings);
        $chada_travel_cancellation_link = self::policy_link(
            (string) ($chada_travel_bundle['cancellation_refund']['url'] ?? ''),
            'Cancellation Policy and Refund Policy',
            'cancellation_refund'
        );
        $chada_travel_policy_panel = '<div class="chada-travel-policy-panel">' . CHADA_TRAVEL_View_Components::check_row(
            'cancellation_refund_consent',
            'I acknowledge the ' . $chada_travel_cancellation_link . '.',
            'chada-travel-policy-consent'
        ) . self::policy_caption($chada_travel_settings) . '</div>';
        $chada_travel_actions = CHADA_TRAVEL_View_Components::actions([
            ['label' => 'Back: Booker and Applicants', 'data' => ['chada-travel-stage-target' => 2]],
            ['label' => 'Cancel Application', 'hidden' => true, 'data' => ['chada-travel-cancel-application' => '']],
            ['label' => 'Next: Payment →', 'variant' => 'primary', 'type' => 'submit'],
        ]);

        return CHADA_TRAVEL_Template::render('frontend/checkout/stage-three', [
            'chada_travel_main_aside_html'   => CHADA_TRAVEL_View_Components::main_aside(
                $chada_travel_booker_panel . $chada_travel_applications_panel,
                $chada_travel_aside
            ),
            'chada_travel_policy_panel_html' => $chada_travel_policy_panel,
            'chada_travel_actions_html'      => $chada_travel_actions,
        ]);
    }

    /**
     * Method cards and Order Summary are static; the selected-method body is populated by
     * chada-travel-checkout.js from chadaTravelCheckoutSettings.payment and the booker's in-session selection, matching
     * the Stage 2/3 pattern of PHP rendering containers that JavaScript fills from server-authoritative data.
     */
    private static function render_stage_four(): string {
        $chada_travel_panels = '';
        $chada_travel_payment_methods = array_keys(CHADA_TRAVEL_Config::get_payment_methods());
        foreach ($chada_travel_payment_methods as $chada_travel_index => $chada_travel_method) {
            $chada_travel_hidden = $chada_travel_index === 0 ? '' : ' hidden';
            $chada_travel_panels .= '<article class="chada-travel-panel chada-travel-payment-panel" data-chada-travel-payment-panel="'
                . $chada_travel_method . '"' . $chada_travel_hidden . '><div class="chada-travel-panel__body" data-chada-travel-payment-body>'
                . '</div></article>';
        }
        $chada_travel_aside = CHADA_TRAVEL_View_Components::panel(
            'Order Summary',
            '<div class="chada-travel-summary-list" id="chada-travel-stage-4-summary"></div>'
        ) . CHADA_TRAVEL_View_Components::notice('Guides are emailed only after payment is confirmed.', 'info');
        $chada_travel_actions = CHADA_TRAVEL_View_Components::actions([
            ['label' => 'Back: Review and Edit', 'data' => ['chada-travel-stage-target' => 3]],
            ['label' => 'Cancel Application', 'hidden' => true, 'data' => ['chada-travel-cancel-application' => '']],
        ]);
        return CHADA_TRAVEL_Template::render('frontend/checkout/stage-four', [
            'chada_travel_main_aside_html' => CHADA_TRAVEL_View_Components::main_aside($chada_travel_panels, $chada_travel_aside),
            'chada_travel_actions_html'    => $chada_travel_actions,
        ]);
    }

    /**
     * Server-renders only the fixed shell (Booking ID banner, disclaimer, and empty content/action containers);
     * chada-travel-checkout.js fills exactly one real status-specific panel from checkout/confirmation, never the
     * wireframe's side-by-side Paid/Pending example cards. Stage 5 does not display the Stage 1-4 progress row
     * (hidden by JavaScript while this stage is shown).
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    private static function render_stage_five(array $chada_travel_settings): string {
        return CHADA_TRAVEL_Template::render('frontend/checkout/stage-five', [
            'chada_travel_transaction_disclaimer' => (string) $chada_travel_settings['chada_travel_transaction_disclaimer'],
            'chada_travel_company_legal_name'     => (string) $chada_travel_settings['chada_travel_company_legal_name'],
        ]);
    }

    /** Cart indicator shared by Stages 1 and 2; JavaScript keeps the accessible count synchronized. */
    private static function render_cart_status(): string {
        return CHADA_TRAVEL_Template::render('frontend/checkout/cart-status');
    }

    /** One native accessible dialog is reused for every destructive customer action. */
    private static function render_confirmation_dialog(): string {
        return CHADA_TRAVEL_Template::render('frontend/checkout/confirmation-dialog');
    }
}
