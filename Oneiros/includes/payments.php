<?php
/**
 * Oneiros — payment gateways for once-off Lucid purchases.
 * PayFast (hosted payment page + ITN) and Paystack (hosted checkout + webhook/verify).
 */
require_once __DIR__ . '/premium.php';

class PayFast
{
    public static function host(): string
    {
        return !empty(Premium::cfg()['payfast_sandbox']) ? 'sandbox.payfast.co.za' : 'www.payfast.co.za';
    }

    /** PayFast signature: fields in order, url-encoded, optional passphrase, MD5. */
    public static function signature(array $data, ?string $passphrase): string
    {
        $pairs = [];
        foreach ($data as $key => $value) {
            if ($key === 'signature' || $value === '' || $value === null) continue;
            $pairs[] = $key . '=' . urlencode(trim((string)$value));
        }
        $string = implode('&', $pairs);
        if ($passphrase !== null && $passphrase !== '') $string .= '&passphrase=' . urlencode(trim($passphrase));
        return md5($string);
    }

    /** Fields for the hosted payment form, in PayFast's documented order. */
    public static function checkout(array $order, string $baseUrl): array
    {
        $cfg = Premium::cfg();
        $data = [
            'merchant_id'      => (string)$cfg['payfast_merchant_id'],
            'merchant_key'     => (string)$cfg['payfast_merchant_key'],
            'return_url'       => $baseUrl . '/oneiros.php?payment=return&order=' . $order['id'],
            'cancel_url'       => $baseUrl . '/oneiros.php?payment=cancel&order=' . $order['id'],
            'notify_url'       => $baseUrl . '/api.php?_route=payments/payfast-itn',
            'email_address'    => $order['buyer_email'],
            'm_payment_id'     => $order['id'],
            'amount'           => number_format($order['amount_cents'] / 100, 2, '.', ''),
            'item_name'        => mb_substr('Oneiros · ' . $order['plan_name'], 0, 100),
            'item_description' => mb_substr($order['kind'] === 'support'
                ? 'A once-off contribution to Oneiros'
                : "{$order['days']} nights of Oneiros Lucid. Once-off payment, never renews.", 0, 255),
        ];
        $data['signature'] = self::signature($data, $cfg['payfast_passphrase'] ?? '');
        return ['provider' => 'payfast', 'method' => 'POST', 'action' => 'https://' . self::host() . '/eng/process', 'fields' => $data];
    }

    /** The ITN parameter string, exactly as received and without the signature. */
    public static function itnParamString(array $post): string
    {
        $pairs = [];
        foreach ($post as $key => $value) {
            if ($key === 'signature') continue;
            $pairs[] = $key . '=' . urlencode((string)$value);
        }
        return implode('&', $pairs);
    }

    public static function validSignature(array $post): bool
    {
        $passphrase = (string)(Premium::cfg()['payfast_passphrase'] ?? '');
        $string = self::itnParamString($post);
        if ($passphrase !== '') $string .= '&passphrase=' . urlencode(trim($passphrase));
        return isset($post['signature']) && hash_equals(md5($string), (string)$post['signature']);
    }

    /** Is the request from one of PayFast's published notification hosts? (logged, see ITN handler) */
    public static function fromPayFast(string $ip): bool
    {
        $valid = [];
        foreach (['www.payfast.co.za', 'sandbox.payfast.co.za', 'w1w.payfast.co.za', 'w2w.payfast.co.za'] as $host) {
            $valid = array_merge($valid, gethostbynamel($host) ?: []);
        }
        return in_array($ip, array_unique($valid), true);
    }

    /** Ask PayFast to confirm the notification is genuine. */
    public static function confirmWithServer(string $paramString): bool
    {
        // Local test seam: only the PHP development server, outside production, with an explicit flag.
        if (PHP_SAPI === 'cli-server' && getenv('ONEIROS_PAYFAST_TEST_CONFIRM') === '1'
            && (Premium::cfg()['environment'] ?? 'production') !== 'production') {
            return true;
        }
        $ch = curl_init('https://' . self::host() . '/eng/query/validate');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $paramString,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 20, CURLOPT_USERAGENT => 'Oneiros/2.1',
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return is_string($response) && trim($response) === 'VALID';
    }
}

class Paystack
{
    private const API = 'https://api.paystack.co';

    private static function request(string $method, string $path, ?array $body = null): array
    {
        $secret = (string)(Premium::cfg()['paystack_secret_key'] ?? '');
        $ch = curl_init(self::API . $path);
        $options = [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $secret, 'Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 25,
        ];
        if ($body !== null) $options[CURLOPT_POSTFIELDS] = json_encode($body);
        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data)) throw new RuntimeException("Paystack returned HTTP $status");
        return $data;
    }

    public static function checkout(array $order, string $baseUrl): array
    {
        $response = self::request('POST', '/transaction/initialize', [
            'email'        => $order['buyer_email'],
            'amount'       => (int)$order['amount_cents'],
            'currency'     => $order['currency'],
            'reference'    => $order['reference'],
            'callback_url' => $baseUrl . '/oneiros.php?payment=return&order=' . $order['id'],
            'metadata'     => [
                'order_id' => $order['id'], 'plan' => $order['plan_id'],
                'cancel_action' => $baseUrl . '/oneiros.php?payment=cancel&order=' . $order['id'],
            ],
        ]);
        if (empty($response['status']) || empty($response['data']['authorization_url'])) {
            throw new RuntimeException($response['message'] ?? 'Paystack could not start the payment');
        }
        Database::query('UPDATE premium_orders SET provider_ref = ? WHERE id = ?', [$order['reference'], $order['id']]);
        return ['provider' => 'paystack', 'method' => 'GET', 'action' => $response['data']['authorization_url']];
    }

    /** Returns the transaction when Paystack confirms it succeeded, else null. */
    public static function verify(string $reference): ?array
    {
        $response = self::request('GET', '/transaction/verify/' . rawurlencode($reference));
        $data = $response['data'] ?? null;
        return (!empty($response['status']) && ($data['status'] ?? '') === 'success') ? $data : null;
    }

    public static function validSignature(string $rawBody, string $signature): bool
    {
        $secret = (string)(Premium::cfg()['paystack_secret_key'] ?? '');
        return $secret !== '' && $signature !== '' && hash_equals(hash_hmac('sha512', $rawBody, $secret), $signature);
    }

    /** Verify with Paystack and fulfil the order if the money arrived. */
    public static function settle(array $order): array
    {
        if ($order['status'] !== 'pending' || $order['provider'] !== 'paystack') return $order;
        $tx = self::verify($order['reference']);
        if (!$tx) return $order;
        if (strtoupper($tx['currency'] ?? '') !== strtoupper($order['currency'])) {
            Premium::logEvent('paystack', $order['id'], 'currency_mismatch', false, (string)($tx['currency'] ?? ''));
            return $order;
        }
        Premium::logEvent('paystack', $order['id'], 'verified', true, 'amount ' . (int)$tx['amount']);
        return Premium::fulfill($order['id'], (string)($tx['id'] ?? $order['reference']), (int)$tx['amount']);
    }
}

/** Absolute app URL (including any subfolder) for gateway return and notify links. */
function oneiros_base_url(): string
{
    return rtrim((string)(Premium::cfg()['frontend_url'] ?? ''), '/');
}
