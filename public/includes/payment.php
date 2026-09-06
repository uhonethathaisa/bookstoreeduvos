<?php
/**
 * Simulated Payment Gateway (external entity).
 * Demo rules so the success AND decline paths can both be exercised:
 *   - number starting with 4 (Visa)  or 5 (Mastercard)   => authorised
 *   - anything else (or malformed)                        => declined
 */
declare(strict_types=1);

function demo_gateway_charge(array $card, float $amount): array
{
    $number = preg_replace('/\D/', '', (string) ($card['number'] ?? ''));

    if ($number === '' || !preg_match('/^\d{15,16}$/', $number)) {
        return ['ok' => false, 'reason' => 'Invalid card number (expected 15–16 digits).'];
    }
    if (!preg_match('/^[45]/', $number)) {
        return ['ok' => false, 'reason' => 'Card declined by the payment gateway. (Demo: use a card starting with 4 or 5.)'];
    }

    $cvv = (string) ($card['cvv'] ?? '');
    if (!preg_match('/^\d{3,4}$/', $cvv)) {
        return ['ok' => false, 'reason' => 'Invalid CVV.'];
    }

    // Expiry MM/YY and not in the past.
    if (!preg_match('#^(0[1-9]|1[0-2])/(\d{2})$#', (string) ($card['expiry'] ?? ''), $m)) {
        return ['ok' => false, 'reason' => 'Expiry must be in MM/YY format.'];
    }
    $expEnd = new DateTime(sprintf('20%02d-%02d-01 23:59:59', (int) $m[2], (int) $m[1]));
    $expEnd->modify('last day of this month');
    if ($expEnd < new DateTime()) {
        return ['ok' => false, 'reason' => 'This card has expired.'];
    }

    return ['ok' => true, 'txn' => 'TXN' . strtoupper(substr(sha1(uniqid('', true)), 0, 8))];
}
