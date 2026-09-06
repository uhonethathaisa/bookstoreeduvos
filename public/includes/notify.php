<?php
/**
 * Simulated transactional e-mail dispatcher (Observer pattern).
 * A real deployment would call an SMTP service (the "Email Service" external
 * entity). Here we persist every message to storage/email.log so the flow can
 * be demonstrated and audited.
 *
 * $kind: 'placed' | 'shipped' | 'completed'
 */
declare(strict_types=1);

function notify_order_email(array $order, array $user, string $kind = 'placed'): void
{
    $lines = [];

    $stmt = db()->prepare(
        'SELECT od.Quantity, od.PriceAtPurchase, b.Title
           FROM order_details od JOIN books b ON b.BookID = od.BookID
          WHERE od.OrderID = ?'
    );
    $stmt->execute([(int) $order['OrderID']]);
    $items = $stmt->fetchAll();

    $orderNo = (int) $order['OrderID'];
    $date    = date('j M Y H:i', strtotime($order['OrderDate']));
    $method  = $order['ShipMethod'] ?? 'Standard';
    $eta     = !empty($order['EstimatedDelivery']) ? date('D j M Y', strtotime($order['EstimatedDelivery'])) : null;

    $subject = match ($kind) {
        'shipped'   => 'Your BookNest order #' . $orderNo . ' has been shipped',
        'completed' => 'Your BookNest order #' . $orderNo . ' has been delivered',
        default     => 'Your BookNest order #' . $orderNo . ' confirmation',
    };

    $lines[] = '==============================================';
    $lines[] = 'TO:       ' . $user['Email'];
    $lines[] = 'SUBJECT:  ' . $subject;
    $lines[] = '----------------------------------------------';
    $lines[] = 'Dear ' . $user['Name'] . ',';
    $lines[] = '';

    switch ($kind) {
        case 'shipped':
            $lines[] = 'Great news — your order #' . $orderNo . ' is on its way!';
            $lines[] = '  Carrier:   ' . ($order['Carrier'] ?? 'BookNest Courier');
            $lines[] = '  Tracking:  ' . ($order['TrackingNumber'] ?? '-');
            $lines[] = '  Method:    ' . $method . ' delivery';
            if ($eta) {
                $lines[] = '  Estimated delivery: ' . $eta;
            }
            break;

        case 'completed':
            $lines[] = 'Your order #' . $orderNo . ' was delivered'
                     . (!empty($order['DeliveredDate']) ? ' on ' . date('j M Y H:i', strtotime($order['DeliveredDate'])) : '') . '.';
            $lines[] = 'We hope you enjoy your books! Please consider leaving a review.';
            break;

        default: // placed
            $lines[] = 'Thank you for shopping with BookNest!';
            $lines[] = 'Your order #' . $orderNo . ' placed on ' . $date
                     . ' is ' . strtolower($order['Status']) . '.';
            $lines[] = '';
            $lines[] = 'Items:';
            foreach ($items as $it) {
                $lines[] = sprintf('  %-45s x%d  %s',
                    substr($it['Title'], 0, 45),
                    (int) $it['Quantity'],
                    number_format((float) $it['PriceAtPurchase'], 2));
            }
            $lines[] = '';
            if ((float) $order['DiscountAmount'] > 0) {
                $lines[] = '  Discount (' . $order['PromoCode'] . '): -' . number_format((float) $order['DiscountAmount'], 2);
            }
            $lines[] = '  Shipping (' . $method . '): R' . number_format((float) ($order['ShipCost'] ?? 0), 2);
            $lines[] = '  TOTAL: R' . number_format((float) $order['TotalAmount'], 2);
            if ($eta) {
                $lines[] = '  Estimated delivery: ' . $eta;
            }
    }

    $lines[] = '';
    $lines[] = 'Deliver to: ' . $order['ShipName'] . ', ' . $order['ShipAddress']
             . ', ' . $order['ShipCity'] . ' ' . $order['ShipPostcode'] . ', ' . $order['ShipCountry'];
    $lines[] = '----------------------------------------------';
    $lines[] = 'This is a simulated e-mail for the coursework demo.';
    $lines[] = '';

    $dir = defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__, 2) . '/storage';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($dir . '/email.log', implode("\n", $lines), FILE_APPEND | LOCK_EX);
}

