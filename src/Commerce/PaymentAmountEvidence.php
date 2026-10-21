<?php
/**
 * Putting the difference between what an order asked for and what the store was
 * paid into words an admin can act on.
 *
 * @package MillionDollarScript\V3\Commerce
 */

namespace MillionDollarScript\V3\Commerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * A paid signal from a store is not a promise about the amount, so the order keeps
 * both figures (see Commerce\Concerns\UpdatesPaymentSources::paid_order_metadata).
 * This only reads that record back: a site owner comparing a customer's receipt
 * with their own should not have to open the order's metadata to find out which of
 * the two the store actually paid.
 *
 * Nothing here changes what happens to the order. A store completes an order it was
 * told to complete, and whether an ad runs at a negotiated price is a decision a
 * site makes.
 */
final class PaymentAmountEvidence
{
    /**
     * A sentence about the difference, or null when there is nothing to say.
     *
     * @param array $metadata Order metadata.
     * @return array{text:string}|null
     */
    public static function describe(array $metadata): ?array
    {
        $record = isset($metadata['provider_amount_mismatch']) && is_array($metadata['provider_amount_mismatch'])
            ? $metadata['provider_amount_mismatch']
            : null;
        if (!$record) {
            return null;
        }

        $paid = trim((string) ($record['paid_total'] ?? '') . ' ' . strtoupper((string) ($record['paid_currency'] ?? '')));
        $asked = trim((string) ($record['asked_total'] ?? '') . ' ' . strtoupper((string) ($record['asked_currency'] ?? '')));
        if ('' === $paid && '' === $asked) {
            return null;
        }

        $text = !empty($record['currency_only'])
            ? sprintf(
                /* translators: 1: currency the store reported, 2: currency the order is priced in */
                __('The store reported paying in %1$s; this order is priced in %2$s. The amounts match.', 'million-dollar-script'),
                $paid,
                $asked
            )
            : sprintf(
                /* translators: 1: amount the store reported, 2: amount the order asked for */
                __('The store reported being paid %1$s; this order asked for %2$s. The store completed the order it was told to, so this is not a reason the ad did not run - compare the two if a customer queries the charge.', 'million-dollar-script'),
                $paid,
                $asked
            );

        $recorded_at = (string) ($record['recorded_at'] ?? '');
        if ('' !== $recorded_at) {
            $text .= ' ' . sprintf(
                /* translators: %s: date the difference was recorded */
                __('Recorded %s.', 'million-dollar-script'),
                $recorded_at
            );
        }

        return ['text' => $text];
    }
}
