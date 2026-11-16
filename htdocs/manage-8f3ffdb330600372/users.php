<?php
require __DIR__ . '/includes/secure.php'; require_full_admin();
$id=(int)($_GET['id'] ?? 0); $account=$id ? q_row('SELECT id,username,email,role,active FROM users WHERE id=?',[$id]) : null;
if ($id && !$account) { http_response_code(404); exit('Account not found.'); }
$values=$account ?? ['username'=>'','email'=>'','role'=>'editor','active'=>1]; $error='';
if (($_SERVER['REQUEST_METHOD'] ?? '')==='POST') {
    $values=['username'=>trim((string)($_POST['username'] ?? '')),'email'=>trim((string)($_POST['email'] ?? '')),'role'=>(string)($_POST['role'] ?? ''),'active'=>isset($_POST['active'])?1:0];
    $password=(string)($_POST['new_password'] ?? '');
    $actor=current_admin();
    $actorHash=(string)q_one('SELECT password_hash FROM users WHERE id=?',[$actor['id']]);
    if (!password_verify((string)($_POST['current_password'] ?? ''),$actorHash)) { $error='Enter your current password to confirm account changes.'; }
    elseif (!preg_match('/^[A-Za-z0-9_.-]{3,60}$/D',$values['username'])) { $error='Usernames must be 3–60 letters, numbers, dots, dashes, or underscores.'; }
    elseif ($values['email']!=='' && (!filter_var($values['email'],FILTER_VALIDATE_EMAIL) || strlen($values['email'])>190)) { $error='Enter a valid email address.'; }
    elseif (!in_array($values['role'],['admin','editor'],true)) { $error='Choose a valid role.'; }
    elseif ((!$account || $password!=='') && (strlen($password)<12 || strlen($password)>72)) { $error='Choose a password between 12 and 72 bytes.'; }
    elseif ($password!=='' && $password!==(string)($_POST['confirm_password'] ?? '')) { $error='The new passwords do not match.'; }
    elseif ($id===(int)$actor['id'] && ($values['role']!=='admin' || !$values['active'] || $password!=='')) { $error='Keep your own administrator account active. Use My account to change your password.'; }
    if ($error==='') {
        db()->beginTransaction();
        try {
            $admins=q_all("SELECT id FROM users WHERE role='admin' AND active=1 ORDER BY id FOR UPDATE");
            $existing=$id ? q_row('SELECT * FROM users WHERE id=? FOR UPDATE',[$id]) : null;
            if ($id && !$existing) { throw new RuntimeException('Account no longer exists.'); }
            if ($existing && $existing['role']==='admin' && (int)$existing['active'] && ($values['role']!=='admin' || !$values['active']) && count($admins)<=1) { throw new RuntimeException('At least one active administrator must remain.'); }
            if ($existing) {
                q('UPDATE users SET username=?,email=?,role=?,active=?,auth_version=auth_version+1'.($password!==''?',password_hash=?':'').' WHERE id=?',
                    array_merge([$values['username'],$values['email'] ?: null,$values['role'],$values['active']],$password!==''?[password_hash($password,PASSWORD_BCRYPT)]:[],[$id]));
            } else {
                q('INSERT INTO users (username,email,role,active,password_hash) VALUES (?,?,?,?,?)',[$values['username'],$values['email'] ?: null,$values['role'],$values['active'],password_hash($password,PASSWORD_BCRYPT)]);
                $id=(int)db()->lastInsertId();
            }
            workspace_audit($existing ? ($password!==''?'reset_password':'update_account') : 'create_account','users',$id,$values['username'].' · '.$values['role'].' · '.($values['active']?'active':'disabled'));
            if ($id===(int)$actor['id']) { $_SESSION['auth_version']=(int)$existing['auth_version']+1; }
            db()->commit(); flash_set('success','Account saved. Existing sessions for this account must sign in again.'); admin_redirect('users.php?id='.$id);
        } catch (PDOException $failure) { db()->rollBack(); $error=$failure->getCode()==='23000'?'That username or email is already in use.':'Could not save this account.'; }
        catch (RuntimeException $failure) { db()->rollBack(); $error=$failure->getMessage(); }
    }
}
$term=trim((string)($_GET['q'] ?? '')); $like='%'.$term.'%';
$total=(int)q_one('SELECT COUNT(*) FROM users WHERE username LIKE ? OR email LIKE ?',[$like,$like]);
$page=min(max(1,(int)($_GET['page'] ?? 1)),max(1,(int)ceil($total/25)));
$users=q_all('SELECT id,username,email,role,active FROM users WHERE username LIKE ? OR email LIKE ? ORDER BY username LIMIT 25 OFFSET '.(($page-1)*25),[$like,$like]);
admin_header('Accounts','users'); ?>
<p class="field-note">Administrators manage the platform. Editors can create and edit their own draft notes; administrators review and publish them. Disabling an account ends its access on its next request.</p>
<form class="filter-bar" method="get"><label class="field field-inline"><span>Find an account</span><input type="search" name="q" value="<?= e($term) ?>" placeholder="Username or email"></label><button class="btn">Search</button><a class="btn" href="users.php">Add account</a></form>
<table class="table"><thead><tr><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($users as $row): ?><tr><td><?= e($row['username']) ?></td><td><?= e($row['email'] ?? '—') ?></td><td><?= e(ucfirst($row['role'])) ?></td><td><?= $row['active']?'Active':'Disabled' ?></td><td><a class="btn btn-sm" href="?id=<?= (int)$row['id'] ?>">Manage</a></td></tr><?php endforeach; ?></tbody></table>
<?php admin_pagination($total,$page); ?>
<section class="panel"><h2><?= $account?'Manage account':'Create account' ?></h2>
<?php if ($error): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="account-form"><?= csrf_field() ?><div class="form-grid">
<label class="field"><span>Username *</span><input name="username" value="<?= e($values['username']) ?>" required maxlength="60" autocomplete="off"></label>
<label class="field"><span>Email</span><input type="email" name="email" value="<?= e($values['email'] ?? '') ?>" maxlength="190" autocomplete="off"></label>
<label class="field"><span>Role</span><select name="role"><option value="editor" <?= $values['role']==='editor'?'selected':'' ?>>Editor — own drafts</option><option value="admin" <?= $values['role']==='admin'?'selected':'' ?>>Administrator — full access</option></select></label>
<label class="field"><span>Account access</span><span><input type="checkbox" name="active" value="1" <?= $values['active']?'checked':'' ?>> Active</span></label>
<label class="field"><span><?= $account?'New password (optional)':'Password *' ?></span><input type="password" name="new_password" autocomplete="new-password" minlength="12" maxlength="72" <?= $account?'':'required' ?>><small>12–72 bytes. Leave blank to keep an existing password.</small></label>
<label class="field"><span>Confirm new password</span><input type="password" name="confirm_password" autocomplete="new-password" maxlength="72"></label>
</div><label class="field"><span>Your current password *</span><input type="password" name="current_password" required autocomplete="current-password"></label><button class="btn btn-primary">Save account</button></form></section>
<?php admin_footer();
