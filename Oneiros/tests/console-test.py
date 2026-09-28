"""Console integration checks: roles, settings, feature switches, maintenance, users, staff, audit.
Use ONLY a disposable test database. Needs one existing admin account:

  1. register an account in the app (or with api-smoke.py's register call)
  2. php tools/console.php admin that@email
  3. ONEIROS_TEST_ADMIN_EMAIL=that@email ONEIROS_TEST_ADMIN_PASSWORD=... \
     python tests/console-test.py http://127.0.0.1:8080 --allow-write-tests

Creates two accounts, changes settings and restores them, and deletes what it created.
"""
import json, os, sys, urllib.request, urllib.error, uuid

if len(sys.argv) < 3 or sys.argv[2] != '--allow-write-tests' or not os.environ.get('ONEIROS_TEST_ADMIN_EMAIL'):
    sys.exit(__doc__)
BASE = sys.argv[1].rstrip('/')
PASSWORD = 'Test-only-console-check-2026!'
checks = 0


def call(route, method='GET', data=None, token=None, expect=200):
    global checks
    headers = {'Accept': 'application/json'}
    if token: headers['Authorization'] = 'Bearer ' + token
    body = None
    if data is not None:
        body = json.dumps(data).encode(); headers['Content-Type'] = 'application/json'
    request = urllib.request.Request(BASE + '/api/' + route, body, headers, method=method)
    try:
        with urllib.request.urlopen(request, timeout=30) as response:
            status, raw = response.status, response.read()
    except urllib.error.HTTPError as error:
        status, raw = error.code, error.read()
    try: result = json.loads(raw)
    except ValueError: raise AssertionError(f'{method} {route}: non-JSON response, status {status}: {raw[:200]!r}')
    assert status in ([expect] if isinstance(expect, int) else expect), f'{method} {route}: {status}, {result}'
    checks += 1
    print(f'PASS {method} {route} ({status})')
    return result


def register(tag):
    email = f'console-{uuid.uuid4().hex[:10]}-{tag}@example.invalid'
    data = call('auth/register', 'POST', {'email': email, 'password': PASSWORD, 'date_of_birth': '1990-01-01',
                                          'research_consent': True, 'tos_accepted': True}, expect=201)
    return email, data['user']['id'], data['access_token']


def login(email, password=PASSWORD):
    return call('auth/login', 'POST', {'email': email, 'password': password})['access_token']


admin = login(os.environ['ONEIROS_TEST_ADMIN_EMAIL'], os.environ['ONEIROS_TEST_ADMIN_PASSWORD'])
created = []
restore = {}
try:
    me = call('console/me', token=admin)
    assert me['user']['role'] == 'admin' and 'finance.view' in me['permissions']
    admin_id = me['user']['id']
    overview = call('console/overview', token=admin)
    assert 'finance' in overview
    health = call('console/health', token=admin)
    assert health['checks'] and health['overall'] in ('ok', 'warn', 'fail')
    assert not any(c['label'] == 'Schema' and c['status'] == 'fail' for c in health['checks']), 'schema not migrated'
    call('console/health', 'POST', token=admin, expect=400)
    call('console/health?action=migrate', 'POST', token=admin)
    call('console/health?logs=1', token=admin)

    settings = {s['key']: s for s in call('console/settings', token=admin)['settings']}
    assert 'payfast_merchant_key' in settings and 'value' not in settings['payfast_merchant_key'], 'secrets must never be sent'
    for key in ['feature_registrations', 'maintenance_mode', 'min_age', 'payfast_merchant_key', 'plans']:
        restore[key] = None  # reset to file/default afterwards

    # Validation
    call('console/settings', 'PATCH', {'changes': {'min_age': 16}}, admin, 422)
    call('console/settings', 'PATCH', {'changes': {'nope': 1}}, admin, 422)
    call('console/settings', 'PATCH', {'changes': {'plans': {'Bad Id': {'name': 'x', 'days': 1, 'price_cents': 900}}}}, admin, 422)
    call('console/settings', 'PATCH', {'changes': {'plans': {'lucid-14': {'name': 'Fortnight', 'days': 14, 'price_cents': 3900}}}}, admin)
    plans = call('payments/plans', token=admin)
    assert 'lucid-14' in json.dumps(plans), 'plan edits must reach the app'

    # Secrets are stored and masked
    saved = {s['key']: s for s in call('console/settings', 'PATCH', {'changes': {'payfast_merchant_key': 'test-merchant-key-123456'}}, admin)['settings']}
    assert saved['payfast_merchant_key']['is_set'] and saved['payfast_merchant_key']['hint'] == '…3456'

    # Feature switch reaches the app
    call('console/settings', 'PATCH', {'changes': {'feature_registrations': False}}, admin)
    closed = call('auth/register', 'POST', {'email': 'closed@example.invalid', 'password': PASSWORD, 'date_of_birth': '1990-01-01',
                                            'research_consent': True, 'tos_accepted': True}, expect=403)
    assert closed.get('feature_off') == 'registrations'
    assert call('capabilities')['registrations'] is False
    call('console/settings', 'PATCH', {'changes': {'feature_registrations': None}}, admin)

    mod_email, mod_id, _ = register('mod'); created.append(mod_id)
    user_email, user_id, user_token = register('dreamer'); created.append(user_id)
    call('console/me', token=user_token, expect=403)

    # Staff: add a moderator from the Console
    call('console/staff', 'POST', {'email': 'nobody@example.invalid', 'role': 'moderator'}, admin, 404)
    call('console/staff', 'POST', {'email': mod_email, 'role': 'moderator'}, admin)
    mod = login(mod_email)
    mod_me = call('console/me', token=mod)
    assert mod_me['user']['role'] == 'moderator' and 'finance.view' not in mod_me['permissions']
    assert 'finance' not in call('console/overview', token=mod)
    mod_settings = {s['key']: s for s in call('console/settings', token=mod)['settings']}
    assert 'payfast_merchant_key' not in mod_settings and 'plans' not in mod_settings, 'moderators must not see financial settings'
    assert mod_settings['maintenance_mode']['editable'] and not mod_settings['frontend_url']['editable']
    call('console/settings', 'PATCH', {'changes': {'min_age': 21}}, mod, 403)
    call('console/settings', 'PATCH', {'changes': {'plans': {}}}, mod, 403)
    call('console/staff', token=mod, expect=403)
    call('console/health?logs=1', token=mod, expect=403)
    call('console/health?action=migrate', 'POST', token=mod, expect=403)

    # Maintenance: dreamers blocked, staff and sign-in still work
    call('console/settings', 'PATCH', {'changes': {'maintenance_mode': True}}, mod)
    blocked = call('dreams', token=user_token, expect=503)
    assert blocked.get('maintenance')
    call('dreams', token=mod)
    call('health')
    login(user_email)
    call('console/settings', 'PATCH', {'changes': {'maintenance_mode': False}}, mod)
    call('dreams', token=user_token)

    # Users: moderator limits
    listing = call('console/users?q=' + user_email, token=mod)
    assert listing['total'] == 1
    call(f'console/users?id={user_id}', token=mod)
    call(f'console/users?id={user_id}&action=suspend', 'POST', {'reason': 'console test'}, mod)
    call('dreams', token=user_token, expect=401)
    call(f'console/users?id={user_id}&action=reactivate', 'POST', {}, mod)
    call(f'console/users?id={admin_id}&action=suspend', 'POST', {}, mod, 403)
    call(f'console/users?id={user_id}&action=lucid_grant', 'POST', {'days': 30}, mod, 403)
    call(f'console/users?id={user_id}&action=delete', 'POST', {'confirm_email': user_email}, mod, 403)
    call(f'console/users?id={user_id}&action=signout', 'POST', {}, mod)

    # Users: admin actions
    granted = call(f'console/users?id={user_id}&action=lucid_grant', 'POST', {'days': 30}, admin)
    assert granted['user']['is_lucid']
    call(f'console/users?id={user_id}&action=lucid_end', 'POST', {}, admin)
    call(f'console/users?id={user_id}&action=edit', 'POST', {'display_name': 'Renamed'}, admin)
    detail = call(f'console/users?id={user_id}', token=admin)['user']
    assert detail['display_name'] == 'Renamed' and 'orders' in detail and len(detail['history']) >= 5
    call(f'console/users?id={admin_id}&action=role', 'POST', {'role': 'dreamer'}, admin, 400)

    # Audit: admins see everything, moderators only their own
    everything = call('console/audit', token=admin)['entries']
    assert any(e['actor_email'] == mod_email for e in everything)
    assert any(e['action'] == 'settings.update' and 'payfast' in (e['target_id'] or '') for e in everything)
    assert 'test-merchant-key' not in json.dumps(everything), 'secret values must never reach the audit log'
    own = call('console/audit', token=mod)['entries']
    assert own and all(e['actor_email'] == mod_email for e in own)

    # Remove the moderator role: Console access ends
    call(f'console/users?id={mod_id}&action=role', 'POST', {'role': 'dreamer'}, admin)
    call('console/me', token=mod, expect=403)

    call(f'console/users?id={user_id}&action=delete', 'POST', {'confirm_email': 'wrong@example.invalid'}, admin, 422)
    for uid, email in [(user_id, user_email), (mod_id, mod_email)]:
        call(f'console/users?id={uid}&action=delete', 'POST', {'confirm_email': email}, admin)
        created.remove(uid)
    print(f'Console checks completed: {checks}')
finally:
    try:
        if restore: call('console/settings', 'PATCH', {'changes': restore}, admin)
    except AssertionError as error:
        print('Could not restore settings:', error)
    for uid in created:
        try:
            email = call(f'console/users?id={uid}', token=admin)['user']['email']
            call(f'console/users?id={uid}&action=role', 'POST', {'role': 'dreamer'}, admin, [200, 400])
            call(f'console/users?id={uid}&action=delete', 'POST', {'confirm_email': email}, admin)
        except AssertionError as error:
            print('Cleanup failed:', error)
