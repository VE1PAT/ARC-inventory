<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$pdo = db();
$currentUser = Auth::requireRole($pdo, ['admin', 'superuser']);
$allowedRoles = Users::rolesForActor($currentUser);

$message = '';
$error = '';
$editId = (int) ($_GET['edit'] ?? 0);
$editUser = $editId > 0 ? Users::find($pdo, $editId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create') {
            Users::create(
                $pdo,
                $currentUser,
                (string) ($_POST['callsign'] ?? ''),
                (string) ($_POST['password'] ?? ''),
                (string) ($_POST['role'] ?? 'member'),
                !empty($_POST['is_active'])
            );
            $message = 'Member created. They must change the temporary password on first login.';
        }

        if ($action === 'update') {
            $id = (int) ($_POST['user_id'] ?? 0);
            $target = Users::find($pdo, $id);
            if (!$target) {
                throw new RuntimeException('User not found.');
            }
            $pass = (string) ($_POST['password'] ?? '');
            Users::update(
                $pdo,
                $currentUser,
                $target,
                (string) ($_POST['role'] ?? 'member'),
                !empty($_POST['is_active']),
                $pass !== '' ? $pass : null
            );
            $message = $pass !== ''
                ? 'Member updated. They must change the reset password on next login.'
                : 'Member updated.';
            $editUser = null;
            $editId = 0;
        }

        if ($action === 'unlock') {
            $id = (int) ($_POST['user_id'] ?? 0);
            Auth::unlock($pdo, $id);
            $target = Users::find($pdo, $id);
            Auth::addSecurityAlert(
                $pdo,
                'account_unlocked',
                $target['callsign'] ?? null,
                'Account unlocked by ' . $currentUser['callsign']
            );
            $message = 'Account unlocked.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
        if ($action === 'update') {
            $editId = (int) ($_POST['user_id'] ?? 0);
            $editUser = Users::find($pdo, $editId);
        }
    }
}

$users = Users::all($pdo);
$superCount = Users::countSuperusers($pdo);

$pageTitle = 'Members';
render_header($pageTitle, $currentUser);
?>
<section class="card">
  <h2>Club logins</h2>
  <?php if ($message !== ''): ?><p class="ok"><?= e($message) ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="bad"><?= e($error) ?></p><?php endif; ?>
  <?php if ($superCount < 2): ?>
    <p class="bad">Warning: fewer than 2 active superusers. Add another to avoid a single point of failure.</p>
  <?php endif; ?>
  <p class="note">No personal membership data is stored here — callsign, role, and login only.</p>
  <p class="note">Temporary passwords you set here must be changed by the member on first login (or after an admin reset).</p>
</section>

<section class="card">
  <h2><?= $editUser ? 'Edit ' . e($editUser['callsign']) : 'Add member' ?></h2>
  <form method="post" action="members.php">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $editUser ? 'update' : 'create' ?>">
    <?php if ($editUser): ?>
      <input type="hidden" name="user_id" value="<?= (int) $editUser['id'] ?>">
      <p class="note">Leave password blank to keep the current password.</p>
    <?php else: ?>
      <label for="callsign">Callsign</label>
      <input id="callsign" name="callsign" type="text" required autocomplete="off">
    <?php endif; ?>

    <label for="password">Password<?= $editUser ? ' (optional reset)' : '' ?></label>
    <input id="password" name="password" type="password" <?= $editUser ? '' : 'required' ?> autocomplete="new-password">

    <label for="role">Role</label>
    <select id="role" name="role">
      <?php foreach ($allowedRoles as $role): ?>
        <option value="<?= e($role) ?>"
          <?= ($editUser['role'] ?? 'member') === $role ? 'selected' : '' ?>>
          <?= e(role_label($role)) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label class="check">
      <input type="checkbox" name="is_active" value="1"
        <?= !$editUser || (int) $editUser['is_active'] === 1 ? 'checked' : '' ?>>
      Active login
    </label>

    <button type="submit"><?= $editUser ? 'Save member' : 'Create member' ?></button>
    <?php if ($editUser): ?>
      <a class="button button-secondary" href="members.php">Cancel</a>
    <?php endif; ?>
  </form>
</section>

<section class="card">
  <h2>All members · <?= count($users) ?></h2>
  <ul class="plain-list">
    <?php foreach ($users as $u): ?>
      <li>
        <div>
          <strong><?= e($u['callsign']) ?></strong>
          · <?= e(role_label((string) $u['role'])) ?>
          · <?= (int) $u['is_active'] === 1 ? 'Active' : 'Inactive' ?>
          <?php if (!empty($u['locked_at'])): ?> · <span class="bad">Locked</span><?php endif; ?>
        </div>
        <div class="btn-row">
          <a class="button button-secondary" href="members.php?edit=<?= (int) $u['id'] ?>">Edit</a>
          <?php if (!empty($u['locked_at'])): ?>
            <form method="post" action="members.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="unlock">
              <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
              <button type="submit">Unlock</button>
            </form>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php render_footer(); ?>
