"""API integration checks. Use ONLY a disposable test database; creates two users.
Usage: python tests/api-smoke.py http://127.0.0.1:8080 --allow-write-tests
"""
import json, sys, urllib.request, urllib.error, uuid

if len(sys.argv) < 3 or sys.argv[2] != '--allow-write-tests':
    sys.exit(__doc__)
BASE = sys.argv[1].rstrip('/')
accounts = []
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
    except ValueError: raise AssertionError(f'{method} {route}: non-JSON response, status {status}')
    assert status in ([expect] if isinstance(expect, int) else expect), f'{method} {route}: {status}, {result}'
    checks += 1
    print(f'PASS {method} {route} ({status})')
    return result

try:
    call('health')
    call('dreams', expect=401)
    for suffix in ['a', 'b']:
        account = call('auth/register', 'POST', {'email': f'test-{uuid.uuid4().hex}-{suffix}@example.invalid', 'password': 'Test-only-dream-journal-2026!', 'display_name': 'Integration Dreamer', 'date_of_birth': '1990-01-01', 'research_consent': True, 'tos_accepted': True}, expect=201)
        accounts.append(account)
    a, b = accounts
    ta, tb = a['access_token'], b['access_token']
    me = call('auth/me', token=ta)
    call('admin/stats', token=ta, expect=403)
    call('auth/profile', 'PATCH', {'display_name': 'A Dreamer', 'region': 'South Africa', 'region_code': 'ZA'}, ta)
    content = 'I was flying above a moonlit ocean. A bright silver moon reflected over the water and a doorway opened into a peaceful forest.'
    dream = call('dreams', 'POST', {'title': 'Moonlit sea', 'content': content, 'privacy': 'public', 'emotions': ['peaceful']}, ta, 201)
    second = call('dreams', 'POST', {'title': 'Silver ocean', 'content': content, 'privacy': 'public'}, tb, 201)
    did = dream['id']
    listing = call('dreams', token=ta)
    assert any(row['id'] == did for row in listing['dreams'])
    call('dreams/' + did, token=ta)
    call('dreams/' + did, 'DELETE', token=tb, expect=[403, 404])
    call('dreams/' + did + '/matches', token=ta)
    call('matches', token=ta)
    call('dreams/' + did + '/privacy', 'PATCH', {'privacy': 'private'}, ta)
    call('dreams/' + did, token=tb, expect=[403, 404])
    for route in ['research/global', 'research/me', 'research/map', 'streaks/me', 'streaks/badges/me', 'streaks/leaderboard', 'notifications']:
        call(route, token=ta)
    connection = call('connections/request', 'POST', {'receiver_id': b['user']['id']}, ta, 201)
    cid = connection['id']
    call('connections/' + cid + '/messages', token=ta, expect=403)
    call('connections/' + cid + '/accept', 'PATCH', {}, tb)
    call('connections', token=ta)
    call('connections/' + cid + '/messages', 'POST', {'content': 'We both dreamed of the ocean.'}, ta, 201)
    messages = call('connections/' + cid + '/messages', token=tb)
    assert any(row['content'] == 'We both dreamed of the ocean.' for row in messages['messages'])
    call('dreams/' + did, 'DELETE', token=ta)
    login = call('auth/login', 'POST', {'email': a['user']['email'], 'password': 'Test-only-dream-journal-2026!'})
    fresh = call('auth/refresh', 'POST', {'refresh_token': login['refresh_token']})
    call('auth/me', token=fresh['access_token'])
    print(f'Integration checks completed: {checks}')
finally:
    for account in accounts:
        try: call('auth/account', 'DELETE', token=account['access_token'])
        except Exception as error: print('Cleanup needs attention:', error)
