"""Lucid payments integration test. DISPOSABLE TEST DATABASE ONLY.

Start a local server with test PayFast credentials and the development-only confirmation flag
(the flag is ignored outside PHP's built-in server and outside non-production environments):

  ONEIROS_ENVIRONMENT=development ONEIROS_PAYFAST_TEST_CONFIRM=1 ONEIROS_PAYFAST_SANDBOX=1 \\
  ONEIROS_PAYFAST_MERCHANT_ID=10000100 ONEIROS_PAYFAST_MERCHANT_KEY=46f0cd694581a ONEIROS_PAYFAST_PASSPHRASE=testpass123 \\
  ONEIROS_PAYSTACK_SECRET_KEY=sk_test_local php -S 127.0.0.1:8091 tools/dev-router.php

Then: python tests/payments-test.py http://127.0.0.1:8091 --allow-write-tests
"""
import hashlib, hmac, json, os, subprocess, sys, urllib.error, urllib.parse, urllib.request, uuid
from pathlib import Path

if len(sys.argv) < 3 or sys.argv[2] != '--allow-write-tests':
    sys.exit(__doc__)
BASE = sys.argv[1].rstrip('/')
ROOT = Path(os.environ.get('ONEIROS_ROOT') or Path(__file__).resolve().parents[1])  # the install whose console to use
PASSPHRASE = 'testpass123'
PW = 'Payments-test-2026!'
checks = 0


def call(route, method='GET', data=None, token=None, expect=200, form=None, headers=None, raw=None):
    global checks
    hdrs = {'Accept': 'application/json', **(headers or {})}
    body = None
    if token: hdrs['Authorization'] = 'Bearer ' + token
    if data is not None:
        body = json.dumps(data).encode(); hdrs['Content-Type'] = 'application/json'
    if form is not None:
        body = urllib.parse.urlencode(form).encode(); hdrs['Content-Type'] = 'application/x-www-form-urlencoded'
    if raw is not None:
        body = raw; hdrs['Content-Type'] = 'application/json'
    req = urllib.request.Request(f'{BASE}/api/{route}', body, hdrs, method=method)
    try:
        with urllib.request.urlopen(req, timeout=30) as r: status, out = r.status, r.read()
    except urllib.error.HTTPError as e: status, out = e.code, e.read()
    try: result = json.loads(out)
    except ValueError: result = out.decode()
    assert status in ([expect] if isinstance(expect, int) else expect), f'{method} {route}: {status} {result}'
    checks += 1
    return result


def ok(label, cond, detail=''):
    global checks
    assert cond, f'{label} {detail}'
    checks += 1
    print('PASS', label, detail)


def php_url_encode(value):
    # PHP urlencode(): spaces as +, everything except A-Za-z0-9 -_. percent-encoded
    return urllib.parse.quote_plus(str(value), safe='-_.')


def payfast_signature(fields, passphrase):
    parts = [f'{k}={php_url_encode(str(v).strip())}' for k, v in fields.items() if k != 'signature' and str(v) != '']
    s = '&'.join(parts)
    if passphrase: s += '&passphrase=' + php_url_encode(passphrase)
    return hashlib.md5(s.encode()).hexdigest()


def itn(order, status='COMPLETE', amount=None, tamper_signature=False):
    fields = {
        'm_payment_id': order['id'], 'pf_payment_id': str(uuid.uuid4().int)[:7], 'payment_status': status,
        'item_name': 'Oneiros · ' + order['plan_name'], 'item_description': '',
        'amount_gross': amount or f"{order['amount_cents'] / 100:.2f}", 'amount_fee': '-2.30', 'amount_net': '66.70',
        'custom_str1': '', 'name_first': 'Test', 'name_last': 'Dreamer', 'email_address': 'dreamer@example.invalid',
        'merchant_id': '10000100',
    }
    # PayFast signs its notification over the string of fields as posted (empty ones included)
    string = '&'.join(f'{k}={php_url_encode(v)}' for k, v in fields.items()) + '&passphrase=' + php_url_encode(PASSPHRASE)
    fields['signature'] = hashlib.md5(string.encode()).hexdigest()
    if tamper_signature: fields['signature'] = '0' * 32
    return call('payments/payfast-itn', 'POST', form=fields, expect=[200, 500])


def register(tag, ref=None):
    body = {'email': f'pay-{tag}-{uuid.uuid4().hex[:8]}@example.invalid', 'password': PW, 'date_of_birth': '1990-01-01',
            'research_consent': True, 'tos_accepted': True, 'region': 'South Africa', 'region_code': 'ZA'}
    if ref: body['referral_code'] = ref
    return call('auth/register', 'POST', body, expect=201)


def console(*args):
    return subprocess.run(['php', 'tools/console.php', *args], cwd=ROOT, capture_output=True, text=True, check=True).stdout.strip()


DREAM = 'I was flying above a moonlit ocean. A silver moon reflected over the water and a glowing doorway opened into a peaceful forest.'
created = []
try:
    plans = call('payments/plans')
    ok('plans listed', len(plans['plans']) >= 3 and 'payfast' in plans['providers'], str([p['id'] for p in plans['plans']]))
    ok('featured plan & savings', any(p['featured'] for p in plans['plans']) and plans['plans'][-1]['saving_percent'] > 0)

    a = register('a'); created.append(a); ta = a['access_token']
    console('admin', a['user']['email'])
    a = call('auth/login', 'POST', {'email': a['user']['email'], 'password': PW}); ta = a['access_token']
    ok('new dreamer is free', call('payments/status', token=ta)['is_premium'] is False)
    baseline = call('admin/revenue', token=ta)['totals']

    # ── Purchase a 30-day pass through PayFast
    co = call('payments/checkout', 'POST', {'plan_id': 'lucid-30'}, ta, expect=201)
    fields = co['redirect']['fields']
    ok('checkout posts to PayFast sandbox', co['redirect']['action'] == 'https://sandbox.payfast.co.za/eng/process')
    ok('checkout signature verifies independently', payfast_signature(fields, PASSPHRASE) == fields['signature'])
    ok('amount formatted for PayFast', fields['amount'] == '69.00', fields['amount'])
    order = co['order']
    ok('order pending before payment', call(f"payments/order?id={order['id']}", token=ta)['order']['status'] == 'pending')

    ok('tampered signature rejected', itn(order, tamper_signature=True) == 'REJECTED')
    ok('wrong amount rejected', itn(order, amount='1.00') == 'REJECTED')
    ok('still unpaid after bad notifications', call(f"payments/order?id={order['id']}", token=ta)['order']['status'] == 'pending')

    ok('genuine notification accepted', itn(order) == 'OK')
    st = call(f"payments/order?id={order['id']}", token=ta)
    ok('order paid', st['order']['status'] == 'paid')
    ok('30 Lucid days granted', st['membership']['is_premium'] and st['membership']['days_left'] == 30, str(st['membership']['days_left']))
    ok('replayed notification is harmless', itn(order) == 'OK' and call('payments/status', token=ta)['days_left'] == 30)

    # ── Buying again stacks on top of remaining time
    co2 = call('payments/checkout', 'POST', {'plan_id': 'lucid-7'}, ta, expect=201)
    itn(co2['order'])
    ok('second pass extends the first', call('payments/status', token=ta)['days_left'] == 37)
    ok('login payload knows about Lucid', call('auth/me', token=ta)['user']['is_premium'] is True)

    # ── Gift a pass
    gift = call('payments/checkout', 'POST', {'plan_id': 'lucid-30', 'gift': True, 'recipient_email': 'friend@example.invalid'}, ta, expect=201)
    itn(gift['order'])
    gift_order = call(f"payments/order?id={gift['order']['id']}", token=ta)['order']
    ok('gift code issued after payment', gift_order['kind'] == 'gift' and gift_order['gift_code'].startswith('GIFT-'), gift_order['gift_code'])
    ok('buyer time unchanged by gift', call('payments/status', token=ta)['days_left'] == 37)
    b = register('b'); created.append(b); tb = b['access_token']
    red = call('payments/redeem', 'POST', {'code': gift_order['gift_code'].lower()}, tb)
    ok('friend redeems gift (case-insensitive)', red['membership']['days_left'] == 30)
    call('payments/redeem', 'POST', {'code': gift_order['gift_code']}, tb, expect=400)
    call('payments/redeem', 'POST', {'code': gift_order['gift_code']}, ta, expect=400)
    ok('gift codes work once', True)

    # ── Discount codes (admin) and promo codes (console)
    half = 'HALF' + uuid.uuid4().hex[:6].upper()
    codes = call('admin/codes', 'POST', {'kind': 'discount', 'percent_off': 50, 'max_uses': 10, 'code': half}, ta, expect=201)
    ok('admin creates discount code', codes['created'] == [half])
    c = register('c'); created.append(c); tc = c['access_token']
    q = call('payments/quote', 'POST', {'plan_id': 'lucid-30', 'code': half.lower()}, tc)
    ok('quote applies 50% off', q['amount_cents'] == 3450 and q['percent_off'] == 50, q['amount'])
    co3 = call('payments/checkout', 'POST', {'plan_id': 'lucid-30', 'code': half}, tc, expect=201)
    ok('discounted checkout amount', co3['redirect']['fields']['amount'] == '34.50')
    itn(co3['order'])
    ok('discounted pass granted', call('payments/status', token=tc)['days_left'] == 30)
    call('payments/quote', 'POST', {'plan_id': 'lucid-30', 'code': half}, tc, expect=400)
    ok('discount usable once per dreamer', True)
    call('payments/redeem', 'POST', {'code': half}, tc, expect=400)
    promo = console('codes', 'promo', '14', '1').splitlines()[-1]
    ok('promo redeem adds 14 days', call('payments/redeem', 'POST', {'code': promo}, tc)['membership']['days_left'] == 44)

    # ── Support (tip jar)
    tip = call('payments/checkout', 'POST', {'plan_id': 'support-5000'}, tc, expect=201)
    itn(tip['order'])
    st = call('payments/status', token=tc)
    ok('supporter becomes Patron without extra days', st['is_patron'] and st['days_left'] == 44)

    # ── Cancelled checkout
    co4 = call('payments/checkout', 'POST', {'plan_id': 'lucid-90'}, tc, expect=201)
    call('payments/cancel', 'POST', {'order_id': co4['order']['id']}, tc)
    ok('cancel marks order', call(f"payments/order?id={co4['order']['id']}", token=tc)['order']['status'] == 'cancelled')

    # ── Referral: both dreamers receive 7 days when the friend saves a first dream
    d = register('d'); created.append(d); td = d['access_token']
    ref = call('auth/me', token=td)['user']['referral_code']
    e = register('e', ref); created.append(e); te = e['access_token']
    ok('invitee not rewarded before a dream', call('payments/status', token=te)['is_premium'] is False)
    call('dreams', 'POST', {'content': DREAM, 'privacy': 'private'}, te, expect=201)
    ok('invitee rewarded after first dream', call('payments/status', token=te)['days_left'] == 7)
    ok('inviter rewarded', call('payments/status', token=td)['days_left'] == 7 and call('payments/status', token=td)['referral']['rewarded'] == 1)
    call('dreams', 'POST', {'content': DREAM + ' Again.', 'privacy': 'private'}, te, expect=201)
    ok('reward only once', call('payments/status', token=td)['days_left'] == 7)

    # ── Free tier: closest five resonances, three requests a week
    f = register('f'); created.append(f); tf = f['access_token']
    others = []
    for i in range(7):
        o = register(f'm{i}'); created.append(o); others.append(o)
        call('dreams', 'POST', {'content': DREAM, 'privacy': 'public', 'emotions': ['wonder']}, o['access_token'], expect=201)
    call('dreams', 'POST', {'content': DREAM, 'privacy': 'public', 'emotions': ['wonder']}, tf, expect=201)
    m = call('matches?limit=50', token=tf)
    ok('free dreamer sees five resonances', len(m['matches']) == 5 and m['locked_count'] == m['total'] - 5 and m['total'] >= 7,
       f"visible={len(m['matches'])} total={m['total']} locked={m['locked_count']}")
    for o in others[:3]:
        call('connections/request', 'POST', {'receiver_id': o['user']['id']}, tf, expect=201)
    lim = call('connections/request', 'POST', {'receiver_id': others[3]['user']['id']}, tf, expect=402)
    ok('fourth weekly request asks for Lucid', lim.get('upgrade') == 'connections')
    console('grant', f['user']['email'], '30')
    m = call('matches?limit=50', token=tf)
    ok('Lucid reveals every resonance', len(m['matches']) == m['total'] and m['locked_count'] == 0, str(m['total']))
    call('connections/request', 'POST', {'receiver_id': others[3]['user']['id']}, tf, expect=201)
    ok('Lucid removes request limit', True)

    # ── Admin revenue, refunds and manual fulfilment
    rev = call('admin/revenue', token=ta)
    gained = rev['totals']['all_time'] - baseline['all_time']
    ok('revenue totals', gained == 6900 + 2900 + 6900 + 3450 + 5000 and rev['totals']['paid_orders'] - baseline['paid_orders'] == 5, f'+{gained} cents')
    call('admin/revenue', token=tb, expect=403)
    call('admin/revenue', 'POST', {'order_id': co2['order']['id'], 'action': 'refund'}, ta)
    ok('refund removes the pass days', call('payments/status', token=ta)['days_left'] == 30)
    pending = call('payments/checkout', 'POST', {'plan_id': 'lucid-7'}, tb, expect=201)
    call('admin/revenue', 'POST', {'order_id': pending['order']['id'], 'action': 'mark_paid'}, ta)
    ok('manual mark-paid fulfils', call('payments/status', token=tb)['days_left'] == 37)
    call('admin/premium', 'POST', {'email': b['user']['email'], 'revoke': True}, ta)
    ok('admin can end a pass', call('payments/status', token=tb)['is_premium'] is False)

    # ── Paystack webhook signature
    call('payments/paystack-webhook', 'POST', raw=b'{"event":"charge.success"}', headers={'X-Paystack-Signature': 'bad'}, expect=401)
    body = json.dumps({'event': 'charge.success', 'data': {'reference': 'NOPE'}}).encode()
    sig = hmac.new(b'sk_test_local', body, hashlib.sha512).hexdigest()
    ok('Paystack signed webhook accepted', call('payments/paystack-webhook', 'POST', raw=body, headers={'X-Paystack-Signature': sig})['received'] is True)

    # ── Expiry reminder three days before the end
    g = register('g'); created.append(g); tg = g['access_token']
    console('grant', g['user']['email'], '2')
    call('payments/status', token=tg)
    notes = call('notifications', token=tg)['notifications']
    ok('expiry reminder sent once', sum(n['type'] == 'premium_expiring' for n in notes) == 1)
    call('payments/status', token=tg)
    ok('no duplicate reminder', sum(n['type'] == 'premium_expiring' for n in call('notifications', token=tg)['notifications']) == 1)

    print(f'\nPayments checks completed: {checks}')
finally:
    for acct in created:
        try:
            tok = call('auth/login', 'POST', {'email': acct['user']['email'], 'password': PW}, expect=[200, 401, 429])
            if isinstance(tok, dict) and tok.get('access_token'): call('auth/account', 'DELETE', token=tok['access_token'], expect=[200, 401])
        except Exception as exc:
            print('cleanup:', exc)
