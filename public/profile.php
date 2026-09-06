<?php
/**
 * Customer profile — personal & security details, default shipping address,
 * payment method (tokenised stub) and order history.
 *
 * =============================================================================
 *  PCI COMPLIANCE — READ BEFORE TOUCHING CARD DATA
 * =============================================================================
 *  The full credit-card number (PAN), CVV and any sensitive authentication
 *  data must NEVER be written to our local MySQL database. Storing them would
 *  put us in PCI-DSS scope and expose card data if the database were ever
 *  compromised.
 *
 *  This implementation therefore:
 *    1. Reads the card fields, validates them server-side and DISCARDS the
 *       PAN + CVV immediately.
 *    2. Persists ONLY a token reference + the non-sensitive last four digits
 *       and expiry — the standard "tokenisation" pattern (Stripe Elements,
 *       PayPal Vault, PayFast, Peach Payments, …).
 *    3. `tokenize_card()` below simulates the gateway call. In production it
 *       would be replaced by a real API call, e.g.:
 *
 *         $stripe = new \Stripe\StripeClient($secret);
 *         $tok    = $stripe->tokens->create(['card' => [...]]);
 *         $pm     = $stripe->paymentMethods->create(['card' => ['token' => $tok->id]]);
 *
 *       and we store ONLY $pm->id / $pm->card->last4 / $pm->card->exp_month.
 *       The CVV travels straight to the gateway over TLS and exists in our app
 *       only in memory for the duration of this request.
 * =============================================================================
 */
require_once __DIR__ . '/includes/init.php';

require_login();
$user = current_user();

$rowQ = db()->prepare('SELECT * FROM users WHERE UserID = ?');
$rowQ->execute([$user['UserID']]);
$dbRow = $rowQ->fetch();
if (!$dbRow) {
    logout_user();
    redirect('login.php');
}

/** Minimal Luhn check used to sanity-check card numbers. */
function luhn_ok(string $digits): bool
{
    $sum = 0;
    $alt = false;
    for ($i = strlen($digits) - 1; $i >= 0; $i--) {
        $d = (int) $digits[$i];
        if ($alt) {
            $d *= 2;
            if ($d > 9) { $d -= 9; }
        }
        $sum += $d;
        $alt = !$alt;
    }
    return $sum % 10 === 0;
}

/**
 * PCI-SAFE demo gateway call — returns ONLY safe fields.
 * (Real integration: POST to Stripe/PayPal/PayFast over TLS, receive a token.)
 */
function tokenize_card(string $number, string $expiry): array
{
    $last4 = substr($number, -4);
    return [
        'token'  => 'tok_demo_' . bin2hex(random_bytes(10)),
        'last4'  => $last4,
        'expiry' => $expiry,
    ];
}

/* =============================================================================
 *  GEO DATA (SA-focused storefront)
 * =========================================================================== */
$provinces = [
    'Gauteng', 'Western Cape', 'Eastern Cape', 'KwaZulu-Natal', 'Free State',
    'Limpopo', 'Mpumalanga', 'Northern Cape', 'North West',
];
$countries = [
    'South Africa', 'Botswana', 'Eswatini', 'Lesotho', 'Namibia', 'Zimbabwe',
    'Mozambique', 'Zambia', 'Angola', 'Nigeria', 'Kenya', 'Tanzania', 'Ghana',
    'United Kingdom', 'United States', 'Australia', 'Canada', 'Germany', 'France', 'Netherlands',
];

/* =============================================================================
 *  FORM STATE / DEFAULTS
 * =========================================================================== */
$errors = [];
$ok     = '';
$fv = [
    'name'     => $user['Name'],
    'email'    => $user['Email'],
    'street'   => (string) ($dbRow['ShipStreet'] ?? ''),
    'city'     => (string) ($dbRow['ShipCity'] ?? ''),
    'province' => (string) ($dbRow['ShipProvince'] ?? 'Gauteng'),
    'postcode' => (string) ($dbRow['ShipPostcode'] ?? ''),
    'country'  => (string) ($dbRow['ShipCountry'] ?? STORE_COUNTRY),
];
$savedCard = $dbRow['CardLast4']
    ? ['last4' => $dbRow['CardLast4'], 'expiry' => $dbRow['CardExpiry'], 'token' => $dbRow['CardToken']]
    : null;
$replaceMode = false;
$removeCard  = false;

/* =============================================================================
 *  POST HANDLING
 * =========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'update_profile') {
    $fv = [
        'name'     => post('name'),
        'email'    => strtolower(post('email')),
        'street'   => post('street'),
        'city'     => post('city'),
        'province' => post('province'),
        'postcode' => post('postcode'),
        'country'  => post('country'),
    ];
    $curPw  = (string) ($_POST['current_password'] ?? '');
    $newPw  = (string) ($_POST['new_password'] ?? '');
    $newPw2 = (string) ($_POST['new_password2'] ?? '');

    /* ---- 1. Personal & security ---- */
    if (mb_strlen($fv['name']) < 2)                       { $errors[] = 'Please enter your full name.'; }
    if (!filter_var($fv['email'], FILTER_VALIDATE_EMAIL)) { $errors[] = 'Please enter a valid e-mail address.'; }
    if ($newPw !== '' && mb_strlen($newPw) < 8)           { $errors[] = 'New password must be at least 8 characters.'; }
    if ($newPw !== '' && $newPw !== $newPw2)              { $errors[] = 'New passwords do not match.'; }

    $emailChanging = $fv['email'] !== strtolower($user['Email']);
    $pwChanging    = $newPw !== '';
    // Require the current password only for sensitive changes (e-mail/password).
    if (($emailChanging || $pwChanging) && $curPw === '') {
        $errors[] = 'Enter your current password to change your e-mail or password.';
    } elseif ($curPw !== '' && !password_verify($curPw, $dbRow['Password'])) {
        $errors[] = 'Your current password is incorrect.';
    }
    if ($errors === []) {
        $check = db()->prepare('SELECT 1 FROM users WHERE Email = ? AND UserID <> ?');
        $check->execute([$fv['email'], $user['UserID']]);
        if ($check->fetch()) { $errors[] = 'That e-mail is already used by another account.'; }
    }

    /* ---- 2. Default shipping address ---- */
    if (mb_strlen($fv['street']) < 5) { $errors[] = 'Please enter your street address (min 5 characters).'; }
    if ($fv['city'] === '')           { $errors[] = 'Please enter your city or town.'; }
    if (!in_array($fv['province'], $provinces, true)) { $errors[] = 'Please choose a valid province.'; }
    if ($fv['postcode'] === '')       { $errors[] = 'Postal code is required.'; }
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 \-]{2,9}$/', $fv['postcode'])) {
        $errors[] = 'Postal code looks invalid (letters/digits/spaces/hyphens, 3–10 chars).';
    }
    if (!in_array($fv['country'], $countries, true)) { $errors[] = 'Please choose a valid country.'; }

    /* ---- 3. Payment method (PAN/CVV never persisted — see PCI block) ---- */
    $replaceMode = post('replace_card') === '1';
    $removeCard  = post('remove_card') === '1';
    $cardDigits  = preg_replace('/\D/', '', post('card_no'));
    $cardName    = post('card_name');
    $cardExpiry  = strtoupper(trim(post('card_expiry')));
    $cardCvv     = post('card_cvv');

    $cardToSave = null; // only token/last4/expiry (or ['clear'=>true]) may be saved
    if ($removeCard && $savedCard) {
        $cardToSave = ['clear' => true];
    } elseif ($replaceMode && $cardDigits !== '') {
        if (mb_strlen($cardName) < 2) { $errors[] = 'Please enter the name on the card.'; }
        if (!preg_match('/^\d{15,16}$/', $cardDigits) || !luhn_ok($cardDigits)) {
            $errors[] = 'Card number is invalid — check the digits and try again.';
        }
        if (!preg_match('#^(0[1-9]|1[0-2])/(\d{2})$#', $cardExpiry, $m)) {
            $errors[] = 'Card expiry must be in MM/YY format, e.g. 12/28.';
        } else {
            $expEnd = (new DateTime('20' . $m[2] . '-' . $m[1] . '-01 23:59:59'))->modify('last day of this month');
            if ($expEnd < new DateTime()) { $errors[] = 'This card has expired.'; }
        }
        if (!preg_match('/^\d{3,4}$/', $cardCvv)) { $errors[] = 'CVV must be 3–4 digits.'; }

        if ($errors === []) {
            $tok = tokenize_card($cardDigits, $cardExpiry); // dummy gateway — real Stripe/PayPal call in production
            $cardToSave = ['token' => $tok['token'], 'last4' => $tok['last4'], 'expiry' => $tok['expiry']];
        }
    } // else: keep the existing tokenised card unchanged

    /* ---- 4. Persist ---- */
    if ($errors === []) {
        $hash = $newPw !== '' ? password_hash($newPw, PASSWORD_DEFAULT) : $dbRow['Password'];

        if ($cardToSave === null) {
            $newToken = $dbRow['CardToken'];
            $newLast4 = $dbRow['CardLast4'];
            $newExp   = $dbRow['CardExpiry'];
        } elseif (isset($cardToSave['clear'])) {
            $newToken = $newLast4 = $newExp = null;
        } else {
            $newToken = $cardToSave['token'];
            $newLast4 = $cardToSave['last4'];
            $newExp   = $cardToSave['expiry'];
        }

        $upd = db()->prepare(
            'UPDATE users
                SET Name = ?, Email = ?, Password = ?,
                    ShipStreet = ?, ShipCity = ?, ShipProvince = ?,
                    ShipPostcode = ?, ShipCountry = ?,
                    CardToken = ?, CardLast4 = ?, CardExpiry = ?
              WHERE UserID = ?'
        );
        $upd->execute([
            $fv['name'], $fv['email'], $hash,
            $fv['street'], $fv['city'], $fv['province'],
            $fv['postcode'], $fv['country'],
            $newToken, $newLast4, $newExp,
            $user['UserID'],
        ]);

        login_user(['UserID' => $user['UserID'], 'Name' => $fv['name'], 'Email' => $fv['email'], 'Role' => $user['Role']]);
        $user  = current_user();
        $dbRow = array_merge($dbRow, $fv, [
            'Password' => $hash, 'CardToken' => $newToken, 'CardLast4' => $newLast4, 'CardExpiry' => $newExp,
        ]);
        $savedCard   = $newLast4 ? ['last4' => $newLast4, 'expiry' => $newExp, 'token' => $newToken] : null;
        $replaceMode = false;
        $ok = 'Your profile was saved. Card details are tokenised — only the last 4 digits are stored.';
    }
}

/* =============================================================================
 *  RENDER DATA
 * =========================================================================== */
$maskedCardNo = $savedCard ? '**** **** **** ' . $savedCard['last4'] : '';
$expiryValue  = $replaceMode && isset($_POST['card_expiry']) ? strtoupper(trim($_POST['card_expiry'])) : ($savedCard['expiry'] ?? '');

$orders = db()->prepare(
    'SELECT o.*, (SELECT COUNT(*) FROM order_details od WHERE od.OrderID = o.OrderID) AS items
       FROM orders o WHERE o.UserID = ? ORDER BY o.OrderDate DESC'
);
$orders->execute([$user['UserID']]);
$orderList = $orders->fetchAll();

$pageTitle = 'My profile';
include __DIR__ . '/includes/header.php';
?>

<div class="profile-page">
  <h1>My profile</h1>

  <?php if ($ok): ?><div class="flash flash-success"><?= e($ok) ?></div><?php endif; ?>
  <?php if ($errors): ?>
    <div class="flash flash-error"><strong>Please fix the following:</strong>
      <ul><?php foreach ($errors as $er) { echo '<li>' . e($er) . '</li>'; } ?></ul></div>
  <?php endif; ?>

  <form method="post" action="profile.php" id="profileForm" data-validate>
    <input type="hidden" name="action" value="update_profile">

    <!-- ============ CARD 1 · PERSONAL & SECURITY ============ -->
    <section class="panel profile-card">
      <h2>Personal &amp; security details</h2>
      <div class="field-row">
        <div class="field">
          <label for="name">Full name</label>
          <input type="text" id="name" name="name" value="<?= e($fv['name']) ?>" required
                 data-rules="required|min:2" minlength="2">
        </div>
        <div class="field">
          <label for="email">E-mail</label>
          <input type="email" id="email" name="email" value="<?= e($fv['email']) ?>" required
                 data-rules="required|email">
        </div>
      </div>

      <hr>
      <p class="muted small">Leave the new-password fields blank to keep your current password.
        Your current password is only needed when changing your e-mail or password.</p>
      <div class="field">
        <label for="current_password">Current password <span class="muted">(for e-mail / password changes)</span></label>
        <input type="password" id="current_password" name="current_password" autocomplete="current-password">
      </div>
      <div class="field-row">
        <div class="field">
          <label for="new_password">New password</label>
          <input type="password" id="new_password" name="new_password" autocomplete="new-password"
                 data-rules="min:8">
        </div>
        <div class="field">
          <label for="new_password2">Repeat new password</label>
          <input type="password" id="new_password2" name="new_password2" autocomplete="new-password"
                 data-rules="match:new_password">
        </div>
      </div>
    </section>

    <!-- ============ CARD 2 · DEFAULT SHIPPING ADDRESS ============ -->
    <section class="panel profile-card">
      <h2>Default shipping address</h2>
      <p class="muted small">Saved here so your delivery details are pre-filled the next time you check out.</p>

      <div class="field">
        <label for="street">Street address</label>
        <input type="text" id="street" name="street" value="<?= e($fv['street']) ?>"
               placeholder="e.g. 10 Main Road, Rosebank" required maxlength="160" data-rules="required|min:5">
      </div>
      <div class="field-row">
        <div class="field">
          <label for="city">City / Town</label>
          <input type="text" id="city" name="city" value="<?= e($fv['city']) ?>"
                 placeholder="e.g. Johannesburg" required maxlength="60" data-rules="required">
        </div>
        <div class="field">
          <label for="province">Province</label>
          <select name="province" id="province" required data-rules="required">
            <?php foreach ($provinces as $p): ?>
              <option value="<?= e($p) ?>" <?= $fv['province'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label for="postcode">Postal / ZIP code</label>
          <input type="text" id="postcode" name="postcode" value="<?= e($fv['postcode']) ?>"
                 placeholder="e.g. 2196" required maxlength="10"
                 pattern="[A-Za-z0-9][A-Za-z0-9 \-]{2,9}"
                 title="3–10 characters: letters, digits, spaces or hyphens" data-rules="required">
        </div>
        <div class="field">
          <label for="country">Country</label>
          <select name="country" id="country" required data-rules="required">
            <?php foreach ($countries as $c): ?>
              <option value="<?= e($c) ?>" <?= $fv['country'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </section>

    <!-- ============ CARD 3 · PAYMENT METHOD (PCI-SAFE) ============ -->
    <section class="panel profile-card">
      <h2>Payment method</h2>
      <p class="muted small">
        <strong>Security:</strong> your full card number and CVV are never stored in our database (PCI-DSS).
        They would be sent to a tokenisation provider (Stripe / PayPal / PayFast) and we keep only the token,
        the last 4 digits and the expiry.
      </p>

      <div class="saved-card-box" id="savedCardBox" <?= $savedCard ? '' : 'hidden' ?>>
        <span class="card-icon" aria-hidden="true">&#128179;</span>
        <div>
          <p class="saved-card-no"><?= $maskedCardNo !== '' ? e($maskedCardNo) : '' ?></p>
          <p class="muted small"><?= $savedCard ? 'Expires ' . e($savedCard['expiry']) : '' ?></p>
        </div>
      </div>

      <div class="toggle-row">
        <label class="check">
          <input type="checkbox" id="replaceCard" name="replace_card" value="1" <?= $replaceMode ? 'checked' : '' ?>>
          <?= $savedCard ? 'Replace saved card' : 'Add a card for faster checkout' ?>
        </label>
        <?php if ($savedCard): ?>
          <label class="check danger-check">
            <input type="checkbox" name="remove_card" id="removeCard" value="1">
            Remove saved card
          </label>
        <?php endif; ?>
      </div>

      <div class="new-card-fields" id="newCardFields" <?= $replaceMode ? '' : 'hidden' ?>>
        <div class="field">
          <label for="card_name">Name on card</label>
          <input type="text" id="card_name" name="card_name" maxlength="60" autocomplete="cc-name"
                 placeholder="Name as printed on the card" data-rules="required">
        </div>
        <div class="field-row">
          <div class="field grow">
            <label for="card_no">Card number</label>
            <input type="text" id="card_no" name="card_no" inputmode="numeric" autocomplete="cc-number"
                   placeholder="**** **** **** 1234" data-rules="required" data-card
                   title="Enter the 15–16 digit card number">
          </div>
          <div class="field" style="max-width:130px">
            <label for="card_expiry">Expiry (MM/YY)</label>
            <input type="text" id="card_expiry" name="card_expiry" inputmode="numeric" maxlength="5"
                   autocomplete="cc-exp" placeholder="12/28" value="<?= e($expiryValue) ?>"
                   pattern="(0[1-9]|1[0-2])\/[0-9]{2}" title="Use MM/YY format, e.g. 12/28"
                   data-rules="required" data-exp>
          </div>
          <div class="field" style="max-width:110px">
            <label for="card_cvv">CVV</label>
            <input type="password" id="card_cvv" name="card_cvv" inputmode="numeric" maxlength="4"
                   autocomplete="cc-csc" placeholder="&#8226;&#8226;&#8226;" data-rules="required" data-cvv
                   title="3–4 digit security code">
          </div>
        </div>
        <p class="hint">The CVV is validated here and never stored. In production the card is tokenised
          by the payment gateway before anything is saved.</p>
      </div>
    </section>

    <div class="form-actions">
      <button type="submit" class="btn btn-dark btn-lg">Save changes</button>
      <p class="muted small">One form — saving updates your personal details, default address and payment method together.</p>
    </div>
  </form>

  <!-- ============ ORDER HISTORY ============ -->
  <section class="panel order-history">
    <h2>Order history</h2>
    <?php if (!$orderList): ?>
      <p class="muted">You have not placed any orders yet.</p>
    <?php else: ?>
      <div class="table-scroll">
        <table class="data-table">
          <thead><tr><th>Order</th><th>Date</th><th>Items</th><th class="num">Total</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($orderList as $o): ?>
              <tr>
                <td>#<?= (int) $o['OrderID'] ?></td>
                <td><?= e(date('j M Y', strtotime($o['OrderDate']))) ?></td>
                <td><?= (int) $o['items'] ?></td>
                <td class="num"><?= money((float) $o['TotalAmount']) ?></td>
                <td><span class="badge badge-<?= e(strtolower($o['Status'])) ?>"><?= e($o['Status']) ?></span></td>
                <td>
                  <a href="confirmation.php?order=<?= (int) $o['OrderID'] ?>">Receipt</a>
                  &middot; <a href="track.php?order=<?= (int) $o['OrderID'] ?>">Track</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>




