"""Exercise admin workflows against an isolated, seeded LOCAL preview.

ANSWERED_TEST_PASSWORD=<preview admin password> python tests/admin_workflows.py
Requires the preview schema migration and a subject with ID 1. Creates QA data.
Never run against production; only loopback URLs are accepted.
"""
import argparse
import html
import http.cookiejar
import os
import re
import secrets
import urllib.error
import urllib.parse
import urllib.request

parser = argparse.ArgumentParser()
parser.add_argument('--base-url', default='http://127.0.0.1:8000')
parser.add_argument('--admin-user', default='preview')
parser.add_argument('--manifest', help='Write created record IDs for local test cleanup')
args = parser.parse_args()
assert urllib.parse.urlsplit(args.base_url).hostname in {'127.0.0.1', 'localhost', '::1'}, 'Only isolated local previews are supported'
password = os.environ['ANSWERED_TEST_PASSWORD']
prefix = 'QA' + secrets.token_hex(4)
admin_path = '/manage-8f3ffdb330600372/'
manifest = {'users': [], 'notes': [], 'papers': []}


class Client:
    def __init__(self):
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def request(self, path, data=None, status=200):
        payload = urllib.parse.urlencode(data).encode() if data is not None else None
        try:
            response = self.opener.open(args.base_url + path, payload)
        except urllib.error.HTTPError as error:
            response = error
        body = response.read().decode('utf-8')
        assert response.status == status, (path, response.status, body[:300])
        assert not any(text in body for text in ['Fatal error:', 'Warning:', 'Deprecated:']), (path, body[:400])
        return body, response.geturl().replace(args.base_url, '')

    def token(self, path):
        body, _ = self.request(admin_path + path)
        return re.search(r'name="csrf" value="([^"]+)"', body).group(1)

    def post(self, path, data, status=200):
        data = dict(data, csrf=self.token('login.php' if path == 'login.php' else 'index.php'))
        return self.request(admin_path + path, data, status)

    def login(self, username, secret, success=True):
        body, path = self.post('login.php', {'username': username, 'password': secret})
        assert ('Incorrect username or password.' not in body) == success, 'Login result'
        return body, path


def hidden(body, name):
    match = re.search(r'name="' + name + r'" value="([^"]*)"', body)
    assert match, name
    return html.unescape(match.group(1))


def editor_values(client, note_id=0, title=None, content='<p>Saved content for workflow checks.</p>'):
    body, path = client.request(admin_path + 'notes/edit.php' + ('?id=' + str(note_id) if note_id else ''))
    values = {'csrf': hidden(body, 'csrf'), 'draft_key': hidden(body, 'draft_key'), 'version': hidden(body, 'version'),
              'id': str(note_id), 'subject_id': '1', 'topic_id': '1', 'title': title or prefix + ' Note', 'content': content,
              'level': 'O Level', 'syllabus_code': 'QA-CODE', 'seo_title': 'Focused algebra practice',
              'seo_description': 'Clear explanations for practising algebra step by step.'}
    return values, path


def save_note(client, note_id=0, publish=False, title=None, content='<p>Saved content for workflow checks.</p>'):
    values, path = editor_values(client, note_id, title, content)
    values['publish' if publish else 'save_draft'] = '1'
    body, location = client.request(path, values)
    assert 'Note could not be saved' not in body and 'changed since you opened' not in body
    new_id = int(urllib.parse.parse_qs(urllib.parse.urlsplit(location).query)['id'][0])
    if not note_id:
        manifest['notes'].append(new_id)
    return new_id, body


admin = Client()
admin.login(args.admin_user, password)
for page in ['users.php', 'profile.php', 'activity.php', 'trash.php']:
    admin.request(admin_path + page)
body, _ = admin.request(admin_path + 'users.php?q=' + urllib.parse.quote(args.admin_user))
admin_id = int(re.search(r'href="\?id=(\d+)"', body).group(1))
body, _ = admin.post('users.php?id=' + str(admin_id), {'username': args.admin_user, 'email': '', 'role': 'editor', 'current_password': password})
assert 'Keep your own administrator account active' in body
print('PASS account screens and protection against disabling/demoting your own administrator account')

editor_password = 'WorkflowOnly!2026'
editors = []
for number in [1, 2]:
    username = prefix + 'editor' + str(number)
    body, location = admin.post('users.php', {'username': username, 'email': '', 'role': 'editor', 'active': '1',
                                'new_password': editor_password, 'confirm_password': editor_password, 'current_password': password})
    user_id = int(urllib.parse.parse_qs(urllib.parse.urlsplit(location).query)['id'][0])
    manifest['users'].append(user_id)
    client = Client()
    client.login(username, editor_password)
    editors.append((client, username, user_id))
editor, username, editor_id = editors[0]
other = editors[1][0]
for page in ['users.php', 'activity.php', 'trash.php', 'subjects/edit.php', 'past-papers/edit.php']:
    editor.request(admin_path + page, status=403)
print('PASS editors cannot access accounts, activity, trash, or resource administration')

note_id, _ = save_note(editor)
other.request(admin_path + 'notes/edit.php?id=' + str(note_id), status=403)
other.request(admin_path + 'notes/history.php?id=' + str(note_id), status=403)
values, path = editor_values(editor, note_id)
for action in ['publish', 'delete']:
    blocked = dict(values)
    blocked['publish' if action == 'publish' else 'form_action'] = '1' if action == 'publish' else 'delete'
    editor.request(path, blocked, status=403)
no_csrf = dict(values)
no_csrf['csrf'] = 'invalid'
editor.request(admin_path + 'actions/autosave.php', no_csrf, status=403)
print('PASS draft ownership, publication/deletion restrictions, and CSRF protection')

values, _ = editor_values(editor, note_id)
values['content'] = '<p>Recovered content</p><script>alert(1)</script>'
values['autosave_action'] = 'save'
editor.request(admin_path + 'actions/autosave.php', values)
import json
body, _ = editor.post('actions/autosave.php', {'id': note_id, 'draft_key': 'note-' + str(note_id), 'autosave_action': 'load'})
recovery = json.loads(body)['draft']
assert '<script' not in recovery['content'] and 'Recovered content' in recovery['content']
other.post('actions/autosave.php', {'id': note_id, 'draft_key': 'note-' + str(note_id), 'autosave_action': 'load'}, status=403)
body, _ = editor.request(admin_path + 'notes/edit.php?id=' + str(note_id))
assert 'Load recovered draft' in body
print('PASS sanitized persistent recovery and ownership checks')

stale, stale_path = editor_values(admin, note_id)
save_note(admin, note_id, publish=True, content='<p>Published workflow content.</p>')
editor.request(admin_path + 'notes/edit.php?id=' + str(note_id), status=403)
body, _ = admin.request(stale_path, dict(stale, save_draft='1'))
assert 'changed since you opened it' in body
slug = re.sub(r'[^a-z0-9]+', '-', (prefix + ' Note').lower()).strip('-')
public = '/index.php?' + urllib.parse.urlencode({'p': '/notes/' + slug})
body, _ = admin.request(public)
assert 'Published workflow content.' in body and 'Focused algebra practice - ZIMSEC' in body and 'O Level' in body
values, _ = editor_values(admin, note_id)
values['content'] = '<p>Autosave must not publish this.</p>'
values['autosave_action'] = 'save'
admin.request(admin_path + 'actions/autosave.php', values)
body, _ = admin.request(public)
assert 'Autosave must not publish this' not in body
print('PASS optimistic edit conflicts, SEO overrides, classification, and autosave isolation from published content')

body, _ = admin.request(admin_path + 'notes/history.php?id=' + str(note_id))
revision = hidden(body, 'revision_id')
version = hidden(body, 'version')
admin.post('notes/history.php?id=' + str(note_id), {'revision_id': revision, 'version': version})
admin.request(public, status=404)
save_note(admin, note_id, publish=True)
admin.post('notes/index.php', {'action': 'delete', 'id': note_id})
admin.request(public, status=404)
body, _ = admin.request('/index.php?' + urllib.parse.urlencode({'p': '/search', 'q': prefix}))
assert 'No results found' in body
body, _ = admin.request('/sitemap.php?section=notes')
assert slug not in body
admin.post('trash.php?type=notes', {'id': note_id, 'action': 'restore'})
admin.request(public, status=404)
print('PASS revision restore to draft, trash exclusion from public/search/sitemap, and safe restoration')
admin.post('notes/index.php', {'action': 'delete', 'id': note_id})
body, _ = admin.post('trash.php?type=notes', {'id': note_id, 'action': 'purge', 'confirmation': 'wrong'})
assert 'Type DELETE to confirm' in body
admin.post('trash.php?type=notes', {'id': note_id, 'action': 'purge', 'confirmation': 'DELETE'})
body, _ = admin.request(admin_path + 'trash.php?type=notes')
assert prefix + ' Note' not in body
print('PASS permanent deletion requires explicit confirmation')

body, location = admin.post('past-papers/edit.php', {'subject_id': '1', 'title': prefix + ' Paper', 'year': '2025', 'exam_board': 'ZIMSEC', 'paper_number': '2',
                           'description': 'Workflow test paper', 'pdf_url': 'https://example.com/sample.pdf', 'level': 'A Level', 'syllabus_code': 'QA-123', 'exam_session': 'November',
                           'seo_title': 'Exam practice resource', 'seo_description': 'A test description for exam practice.'})
body, _ = admin.request(admin_path + 'past-papers/index.php?q=' + prefix)
paper_id = int(re.search(r'edit.php\?id=(\d+)', body).group(1))
manifest['papers'].append(paper_id)
paper_slug = re.sub(r'[^a-z0-9]+', '-', (prefix + ' Paper').lower()).strip('-')
paper_public = '/index.php?' + urllib.parse.urlencode({'p': '/past-papers/' + paper_slug})
body, _ = admin.request(paper_public)
assert 'A Level' in body and 'QA-123' in body and 'November' in body and 'Exam practice resource - ZIMSEC' in body
admin.post('past-papers/index.php', {'action': 'delete', 'id': paper_id})
admin.request(paper_public, status=404)
admin.post('trash.php?type=papers', {'id': paper_id, 'action': 'restore'})
admin.request(paper_public)
print('PASS paper metadata, custom SEO, trash, and restore')

for n in range(26):
    save_note(admin, title=prefix + ' Pagination ' + str(n))
query = urllib.parse.urlencode({'q': prefix + ' Pagination', 'sort': 'title'})
body, _ = admin.request(admin_path + 'notes/index.php?' + query + '&page=2')
assert '26 results' in body and 'Page 2 of 2' in body
print('PASS searchable, sorted pagination beyond one page')

admin.post('users.php?id=' + str(editor_id), {'username': username, 'email': '', 'role': 'editor', 'current_password': password})
body, _ = editor.request(admin_path + 'index.php')
assert 'Welcome back.' in body
Client().login(username, editor_password, success=False)
new_password = 'ResetWorkflow!2026'
admin.post('users.php?id=' + str(editor_id), {'username': username, 'email': '', 'role': 'editor', 'active': '1', 'new_password': new_password,
           'confirm_password': new_password, 'current_password': password})
editor.login(username, new_password)
old_session = Client()
old_session.login(username, new_password)
changed = 'ChangedWorkflow!2026'
editor.post('profile.php', {'current_password': new_password, 'new_password': changed, 'confirm_password': changed})
editor.request(admin_path + 'profile.php')
body, _ = old_session.request(admin_path + 'index.php')
assert 'Welcome back.' in body
body, _ = admin.request(admin_path + 'activity.php?q=' + username)
assert username in body and 'create account' in body and editor_password not in body and new_password not in body
print('PASS account disabling, reset, password changes, session revocation, and activity without passwords')
if args.manifest:
    with open(args.manifest, 'w', encoding='utf-8') as output:
        json.dump(manifest, output)
print('All admin workflow checks passed.')
