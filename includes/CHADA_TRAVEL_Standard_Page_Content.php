<?php
/**
 * Generates the generic, review-before-launch starter content written into the three auto-provisioned Policies &
 * Consent pages (see CHADA_TRAVEL_Page_Provisioner). Deliberately avoids inventing any business-specific number (refund
 * percentages, cancellation windows) or a specific governing-law jurisdiction the business has not configured -
 * every number quoted below is a real value already present on CHADA_TRAVEL_Config::get_settings(), never a guess.
 * Gutenberg block-comment markup is used throughout so an administrator editing the page in the block editor
 * gets normal, separately-editable blocks rather than one opaque Classic HTML blob.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Standard_Page_Content {
    /** @param array<string, mixed> $chada_travel_settings CHADA_TRAVEL_Config::get_settings() result. */
    public static function privacy_policy_content(array $chada_travel_settings): string {
        $chada_travel_company = self::company_name($chada_travel_settings);
        $chada_travel_email   = self::support_email($chada_travel_settings);

        return self::notice()
            . self::h2('Introduction')
            . self::p(sprintf(
                '%s ("we", "us", or "our") provides visa application assistance and processing services through '
                    . 'this website. This Privacy Policy explains what information we collect from Bookers and '
                    . 'Applicants, why we collect it, and how it is used and protected.',
                $chada_travel_company
            ))
            . self::h2('Information We Collect')
            . self::p('When you start and complete a visa application booking, we collect:')
            . self::ul([
                'Booker identity and contact details (name, email address, mobile number, and address).',
                'Applicant identity details (name, date of birth, and other information required by the '
                    . 'destination country\'s visa checklist).',
                'Travel documents and supporting files you upload for each visa application.',
                'Payment-related information, such as a payment confirmation, transaction reference, or bank '
                    . 'deposit slip. Full card or bank account numbers are handled directly by our payment '
                    . 'providers and are never stored on this website.',
            ])
            . self::h2('How We Use Your Information')
            . self::p(
                'We use the information collected to process your visa application booking, verify submitted '
                    . 'documents, process payment, communicate with you about the status of your booking and '
                    . 'applications, and meet the requirements of the destination country\'s visa or embassy '
                    . 'process where applicable.'
            )
            . self::h2('How We Share Your Information')
            . self::p(
                'We share information only as needed to provide the service: with our payment providers to '
                    . 'process payment, and, where a visa application requires it, with the relevant embassy, '
                    . 'consulate, or visa processing authority. We do not sell your information to third parties.'
            )
            . self::h2('Data Retention')
            . self::p(
                'We retain booking and application records for as long as needed to provide the service, meet '
                    . 'legal or accounting obligations, and resolve any disputes, in line with the retention '
                    . 'settings configured for this site.'
            )
            . self::h2('Security')
            . self::p(
                'We use reasonable technical and organizational measures to protect the information you share '
                    . 'with us against unauthorized access, loss, or misuse.'
            )
            . self::h2('Your Rights')
            . self::p(sprintf(
                'You may request access to, correction of, or deletion of your personal information by '
                    . 'contacting us at %s, subject to any records we are required to keep.',
                $chada_travel_email
            ))
            . self::h2('Cookies')
            . self::p(
                'This website uses standard WordPress cookies needed to keep you signed in to your booking '
                    . 'session and to keep the site secure. We do not use cookies to track you across other '
                    . 'websites.'
            )
            . self::h2('Children\'s Privacy')
            . self::p(
                'This service is intended for use by adults booking visa applications, including on behalf of '
                    . 'minor Applicants where permitted. We do not knowingly collect information directly from '
                    . 'children outside of that context.'
            )
            . self::h2('Changes to This Policy')
            . self::p(self::version_sentence($chada_travel_settings))
            . self::h2('Contact Us')
            . self::p(sprintf('Questions about this Privacy Policy can be sent to %s.', $chada_travel_email));
    }

    /** @param array<string, mixed> $chada_travel_settings CHADA_TRAVEL_Config::get_settings() result. */
    public static function terms_conditions_content(array $chada_travel_settings): string {
        $chada_travel_company     = self::company_name($chada_travel_settings);
        $chada_travel_email       = self::support_email($chada_travel_settings);
        $chada_travel_draft_hours = CHADA_TRAVEL_Booking_Workflow_Config::draft_expiry_hours($chada_travel_settings);

        return self::notice()
            . self::h2('Acceptance of These Terms')
            . self::p(
                'By starting or completing a booking through this website, you agree to these Terms and '
                    . 'Conditions on behalf of yourself and, where applicable, the Applicants included in your '
                    . 'booking. If you do not agree, please do not use this service.'
            )
            . self::h2('Description of Service')
            . self::p(sprintf(
                '%1$s provides visa application assistance and processing services: helping Bookers prepare, '
                    . 'submit, and track visa applications and required documents. %1$s is not a government '
                    . 'agency, embassy, or consulate, and is not affiliated with any destination country\'s '
                    . 'immigration authority.',
                $chada_travel_company
            ))
            . self::h2('Eligibility and Accuracy of Information')
            . self::p(
                'You are responsible for the accuracy and completeness of the information and documents you '
                    . 'submit for yourself and for every Applicant included in your booking. Incorrect or '
                    . 'incomplete information may delay or prevent processing.'
            )
            . self::h2('Fees and Payment')
            . self::p(
                'Applicable processing fees are shown at checkout before you confirm your booking. We accept '
                    . 'the payment methods offered at checkout. A booking is only confirmed once payment is '
                    . 'received and, where applicable, verified.'
            )
            . self::p(sprintf(
                'A booking that is started but not completed is held as a draft for a limited time (currently '
                    . '%d hours) before it automatically expires and must be started again.',
                $chada_travel_draft_hours
            ))
            . self::h2('Document Requirements')
            . self::p(
                'Each destination country has its own visa document checklist. You are responsible for '
                    . 'providing genuine, valid, and legible documents within any upload window shown to you '
                    . 'during the booking process.'
            )
            . self::h2('Processing Times')
            . self::p(
                'Processing times vary by destination country and by the relevant embassy, consulate, or '
                    . 'immigration authority, and are outside our control once an application has been '
                    . 'submitted to them.'
            )
            . self::h2('No Guarantee of Approval')
            . self::p(sprintf(
                '%s helps prepare and submit your visa application but does not control, and cannot guarantee, '
                    . 'the outcome of any visa application. Approval, refusal, and processing time are decided '
                    . 'solely by the relevant embassy, consulate, or immigration authority.',
                $chada_travel_company
            ))
            . self::h2('Cancellations and Refunds')
            . self::p(
                'Cancellation and refund eligibility are described in our separate Cancellation and Refund '
                    . 'Policy.'
            )
            . self::h2('Prohibited Use')
            . self::p(
                'You agree not to submit false information or fraudulent documents, and not to use this '
                    . 'website for any unlawful purpose.'
            )
            . self::h2('Intellectual Property')
            . self::p(sprintf(
                'All content on this website, other than documents you upload, belongs to %s or its licensors '
                    . 'and may not be copied or reused without permission.',
                $chada_travel_company
            ))
            . self::h2('Limitation of Liability')
            . self::p(sprintf(
                'To the fullest extent permitted by law, %s is not liable for indirect or consequential losses '
                    . 'arising from your use of this service, including losses arising from a visa application '
                    . 'being delayed, refused, or otherwise not approved.',
                $chada_travel_company
            ))
            . self::h2('Governing Law')
            . self::p(
                'These Terms are governed by the laws of the country in which we operate. If you have '
                    . 'questions about which laws apply to your booking, please contact us.'
            )
            . self::h2('Changes to These Terms')
            . self::p(self::version_sentence($chada_travel_settings))
            . self::h2('Contact Us')
            . self::p(sprintf('Questions about these Terms can be sent to %s.', $chada_travel_email));
    }

    /** @param array<string, mixed> $chada_travel_settings CHADA_TRAVEL_Config::get_settings() result. */
    public static function cancellation_refund_content(array $chada_travel_settings): string {
        $chada_travel_company = self::company_name($chada_travel_settings);
        $chada_travel_email   = self::support_email($chada_travel_settings);

        return self::notice()
            . self::h2('Overview')
            . self::p(sprintf(
                'This policy explains how to cancel a visa application booking with %s and when a refund may '
                    . 'apply. It works together with our Terms and Conditions.',
                $chada_travel_company
            ))
            . self::h2('How to Cancel')
            . self::p(sprintf(
                'To cancel a booking or an individual Applicant\'s application, contact us at %s as soon as '
                    . 'possible, including your booking reference.',
                $chada_travel_email
            ))
            . self::h2('Refund Eligibility')
            . self::p(
                'Refund eligibility depends on how far your application has progressed at the time you request '
                    . 'a cancellation - for example, whether documents have already been reviewed or the '
                    . 'application has already been submitted to the relevant embassy, consulate, or '
                    . 'immigration authority. Generally, the earlier you cancel, the more of your payment is '
                    . 'refundable.'
            )
            . self::h2('Non-Refundable Amounts')
            . self::p(
                'Amounts we have already paid to a third party on your behalf - such as government, embassy, '
                    . 'consulate, or payment-processor fees - are typically non-refundable once incurred, even '
                    . 'if your application is later cancelled.'
            )
            . self::h2('Denied or Unsuccessful Applications')
            . self::p(
                'Our processing fee covers the service of preparing and submitting your application, not a '
                    . 'guaranteed outcome. If a visa application is refused or otherwise unsuccessful, the '
                    . 'processing fee is generally not refundable, since the service itself was carried out.'
            )
            . self::h2('How Refunds Are Processed')
            . self::p(
                'Approved refunds are returned using the same payment method used for the original payment '
                    . 'wherever possible. For a Bank or Digital Wallet payment, we may ask you to confirm refund '
                    . 'details before processing.'
            )
            . self::h2('Multi-Applicant Bookings')
            . self::p(
                'A booking that includes more than one Applicant can be cancelled in full or for individual '
                    . 'Applicants only; any refund is calculated per Applicant based on that Applicant\'s own '
                    . 'progress at the time of cancellation.'
            )
            . self::h2('Contact Us')
            . self::p(sprintf('Questions about cancellations or refunds can be sent to %s.', $chada_travel_email));
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function company_name(array $chada_travel_settings): string {
        $chada_travel_name = (string) ($chada_travel_settings['chada_travel_company_name'] ?? '');
        return $chada_travel_name !== '' ? $chada_travel_name : CHADA_TRAVEL_PROJECT_NAME;
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function support_email(array $chada_travel_settings): string {
        $chada_travel_email = (string) ($chada_travel_settings['chada_travel_company_support_email'] ?? '');
        return $chada_travel_email !== '' ? $chada_travel_email : 'the support address listed on this website';
    }

    /**
     * States the current Policy Version/Effective Date when both are already resolved; a safe generic fallback
     * otherwise.
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    private static function version_sentence(array $chada_travel_settings): string {
        $chada_travel_version = (string) ($chada_travel_settings['chada_travel_policy_version'] ?? '');
        $chada_travel_date    = (string) ($chada_travel_settings['chada_travel_policy_effective_date'] ?? '');
        if ($chada_travel_version === '' || $chada_travel_date === '') {
            return 'We may update this policy from time to time. Continued use of this website after a change '
                . 'means you accept the revised policy.';
        }
        return sprintf(
            'This is Policy Version %1$s, effective %2$s. We may update this policy from time to time; a new '
                . 'Policy Version and Effective Date will be published here whenever we do.',
            $chada_travel_version,
            $chada_travel_date
        );
    }

    /** Invisible on the front end, visible in the block editor - flags every generated page as a starting point. */
    private static function notice(): string {
        return '<!-- chada-travel: auto-generated starter content, review before launch -->' . "\n\n";
    }

    private static function h2(string $chada_travel_text): string {
        return '<!-- wp:heading -->' . "\n" . '<h2>' . self::esc($chada_travel_text) . '</h2>' . "\n"
            . '<!-- /wp:heading -->' . "\n\n";
    }

    private static function p(string $chada_travel_text): string {
        return '<!-- wp:paragraph -->' . "\n" . '<p>' . self::esc($chada_travel_text) . '</p>' . "\n"
            . '<!-- /wp:paragraph -->' . "\n\n";
    }

    /** @param list<string> $chada_travel_items */
    private static function ul(array $chada_travel_items): string {
        $chada_travel_html = '<!-- wp:list -->' . "\n" . '<ul class="wp-block-list">';
        foreach ($chada_travel_items as $chada_travel_item) {
            $chada_travel_html .= '<li>' . self::esc($chada_travel_item) . '</li>';
        }
        return $chada_travel_html . '</ul>' . "\n" . '<!-- /wp:list -->' . "\n\n";
    }

    private static function esc(string $chada_travel_text): string {
        return function_exists('esc_html') ? esc_html($chada_travel_text) : htmlspecialchars($chada_travel_text, ENT_QUOTES, 'UTF-8');
    }
}
