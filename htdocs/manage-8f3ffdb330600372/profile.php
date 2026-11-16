<?php
require __DIR__ . '/includes/secure.php';
$user=current_admin(); $error='';
if (($_SERVER['REQUEST_METHOD'] ?? '')==='POST') {
    $hash=(string)q_one('SELECT password_hash FROM users WHERE id=?',[$user['id']]);
    $password=(string)($_POST['new_password'] ?? '');
    if (!password_verify((string)($_POST['current_password'] ?? ''),$hash)) { $error='Your current password is incorrect.'; }
    elseif (strlen($password)<12 || strlen($password)>72) { $error='Choose a new password between 12 and 72 bytes.'; }
    elseif ($password!==(string)($_POST['confirm_password'] ?? '')) { $error='The new passwords do not match.'; }
    else {
        db()->beginTransaction();
        try {
            q('UPDATE users SET password_hash=?,auth_version=auth_version+1 WHERE id=?',[password_hash($password,PASSWORD_BCRYPT),$user['id']]);
            workspace_audit('change_password','users',(int)$user['id'],'Changed own password');
            $version=(int)q_one('SELECT auth_version FROM users WHERE id=?',[$user['id']]);
            db()->commit(); session_regenerate_id(true); $_SESSION['auth_version']=$version;
            flash_set('success','Password changed. Other sessions for your account have been signed out.'); admin_redirect('profile.php');
        } catch (Throwable $failure) { db()->rollBack(); $error='Could not change your password. Try again.'; }
    }
}
admin_header('My account','profile'); ?>
<section class="form-card"><h2><?= e($user['username']) ?></h2><p class="field-note"><?= e(ucfirst($user['role'])) ?> account. Changing your password signs out your other sessions.</p>
<?php if ($error): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post"><?= csrf_field() ?><label class="field"><span>Current password</span><input type="password" name="current_password" required autocomplete="current-password"></label><label class="field"><span>New password</span><input type="password" name="new_password" required minlength="12" maxlength="72" autocomplete="new-password"></label><label class="field"><span>Confirm new password</span><input type="password" name="confirm_password" required maxlength="72" autocomplete="new-password"></label><button class="btn btn-primary">Change password</button></form></section>
<?php admin_footer();
