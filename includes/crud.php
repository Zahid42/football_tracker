<?php
/**
 * Small generic CRUD engine for the simple admin pages (teams, venues, trophies ...).
 * A page describes its table + fields in an array and calls crud_run($cfg).
 *
 * Field keys: name, label, type (text|textarea|number|date|time|select|checkbox|image),
 *   required, options (array|callable), nullable, min, max, step, default, help, col (bootstrap col width), dir (image folder)
 */
declare(strict_types=1);

function crud_options(array $f): array
{
    $o = $f['options'] ?? [];
    return is_callable($o) ? $o() : $o;
}

/** Validate + read POST/FILES for $fields. Returns [data, errors]. */
function crud_collect(array $fields, array $existing = []): array
{
    $data = []; $errors = []; $imageFields = [];
    foreach ($fields as $f) {
        $n = $f['name']; $label = $f['label']; $type = $f['type'] ?? 'text';
        $req = !empty($f['required']);
        if ($type === 'image') { $imageFields[] = $f; continue; }
        if ($type === 'checkbox') { $data[$n] = isset($_POST[$n]) ? 1 : 0; continue; }
        $v = post($n);
        if ($v === '') {
            if ($req) { $errors[] = "$label is required."; }
            $data[$n] = null;
            continue;
        }
        switch ($type) {
            case 'number':
                if (!is_numeric($v)) { $errors[] = "$label must be a number."; break; }
                $num = $v + 0;
                if (isset($f['min']) && $num < $f['min']) { $errors[] = "$label must be at least {$f['min']}."; }
                if (isset($f['max']) && $num > $f['max']) { $errors[] = "$label must be at most {$f['max']}."; }
                $data[$n] = $num;
                break;
            case 'date':
                $d = DateTime::createFromFormat('Y-m-d', $v);
                if (!$d || $d->format('Y-m-d') !== $v) { $errors[] = "$label is not a valid date."; } else { $data[$n] = $v; }
                break;
            case 'time':
                if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v)) { $errors[] = "$label is not a valid time."; } else { $data[$n] = $v . ':00'; }
                break;
            case 'select':
                $opts = crud_options($f);
                $isList = array_is_list($opts);
                $valid = $isList ? in_array($v, $opts, true) : array_key_exists($v, $opts);
                if (!$valid) { $errors[] = "$label has an invalid value."; } else { $data[$n] = $isList ? $v : (is_numeric($v) ? (int)$v : $v); }
                break;
            default:
                $max = $f['max'] ?? ($type === 'textarea' ? 5000 : 255);
                if (mb_strlen($v) > $max) { $errors[] = "$label is too long (max $max characters)."; }
                $data[$n] = $v;
        }
    }
    $uploaded = [];
    if (!$errors) {
        foreach ($imageFields as $f) {
            try {
                $path = handle_image_upload($f['name'], $f['dir']);
            } catch (RuntimeException $ex) {
                $errors[] = $f['label'] . ': ' . $ex->getMessage();
                foreach ($uploaded as $u) { delete_upload($u); }
                $uploaded = [];
                break;
            }
            if ($path !== null) { $data[$f['name']] = $path; $uploaded[] = $path; }
            elseif (!empty($f['required']) && empty($existing[$f['name']])) { $errors[] = $f['label'] . ' is required.'; }
        }
    }
    return [$data, $errors, $uploaded];
}

function crud_render_fields(array $fields, array $values = []): string
{
    $h = '<div class="row g-3">';
    foreach ($fields as $f) {
        $n = $f['name']; $type = $f['type'] ?? 'text';
        $val = $values[$n] ?? ($f['default'] ?? '');
        $col = $f['col'] ?? ($type === 'textarea' ? 12 : 6);
        $req = !empty($f['required']) ? ' required' : '';
        $star = !empty($f['required']) ? ' <span class="text-danger">*</span>' : '';
        $h .= '<div class="col-12 col-md-' . (int)$col . '">';
        if ($type === 'checkbox') {
            $h .= '<div class="form-check form-switch mt-4"><input class="form-check-input" type="checkbox" role="switch" id="f_' . e($n) . '" name="' . e($n) . '" value="1"' . ($val ? ' checked' : '') . '>'
                . '<label class="form-check-label" for="f_' . e($n) . '">' . e($f['label']) . '</label></div>';
        } else {
            $h .= '<label class="form-label" for="f_' . e($n) . '">' . e($f['label']) . $star . '</label>';
            $id = ' id="f_' . e($n) . '" name="' . e($n) . '"';
            switch ($type) {
                case 'textarea':
                    $h .= '<textarea class="form-control" rows="' . (int)($f['rows'] ?? 3) . '"' . $id . $req . '>' . e($val) . '</textarea>'; break;
                case 'select':
                    $h .= '<select class="form-select"' . $id . $req . '>';
                    if (empty($f['required']) || !empty($f['nullable'])) { $h .= '<option value="">-- none --</option>'; }
                    $opts = crud_options($f); $isList = array_is_list($opts);
                    foreach ($opts as $k => $label) {
                        $key = $isList ? $label : $k;
                        $h .= '<option value="' . e($key) . '"' . ((string)$val === (string)$key ? ' selected' : '') . '>' . e($label) . '</option>';
                    }
                    $h .= '</select>'; break;
                case 'number':
                    $h .= '<input type="number" class="form-control"' . $id . ' value="' . e($val) . '"'
                        . (isset($f['min']) ? ' min="' . e($f['min']) . '"' : '') . (isset($f['max']) ? ' max="' . e($f['max']) . '"' : '')
                        . ' step="' . e($f['step'] ?? '1') . '"' . $req . '>'; break;
                case 'date':
                    $h .= '<input type="date" class="form-control"' . $id . ' value="' . e($val) . '"' . $req . '>'; break;
                case 'time':
                    $h .= '<input type="time" class="form-control"' . $id . ' value="' . e($val ? substr((string)$val, 0, 5) : '') . '"' . $req . '>'; break;
                case 'image':
                    if (!empty($values[$n])) {
                        $h .= '<div class="mb-2"><img src="' . e(upload_url($values[$n])) . '" class="img-thumb" alt=""></div>';
                    }
                    $h .= '<input type="file" class="form-control"' . $id . ' accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">'
                        . '<div class="form-text">JPG, PNG or WEBP, max ' . (MAX_UPLOAD_BYTES / 1048576) . ' MB.' . (!empty($values[$n]) ? ' Leave empty to keep the current image.' : '') . '</div>';
                    break;
                default:
                    $h .= '<input type="text" class="form-control"' . $id . ' value="' . e($val) . '" maxlength="' . (int)($f['max'] ?? 255) . '"' . $req . '>';
            }
            if (!empty($f['help'])) { $h .= '<div class="form-text">' . e($f['help']) . '</div>'; }
        }
        $h .= '</div>';
    }
    return $h . '</div>';
}

/** Thumbnail cell. */
function thumb(?string $rel, string $dir = ''): string
{
    return $rel ? '<img src="' . e(upload_url($rel)) . '" class="img-thumb-sm" alt="">' : '<span class="text-muted-ft">-</span>';
}

/**
 * Run a whole admin CRUD page (list / new / edit / delete).
 * $cfg: table, title, singular, icon, fields[], columns[] (label, key|callable, html?, class?), order, search[], select (SQL using alias "t"),
 *       before_save(array &$data, ?array $existing): ?string, after_save(int $id, array $data, bool $isNew), intro
 */
function crud_run(array $cfg): void
{
    $admin = require_login();
    $table = $cfg['table']; $fields = $cfg['fields'];
    $action = get_str('action', 'list');
    $id = (int)($_GET['id'] ?? 0);
    $self = basename($_SERVER['SCRIPT_NAME']);
    $existing = null;
    $errors = [];

    $load = function (int $id) use ($table): ?array {
        $st = db()->prepare("SELECT * FROM `$table` WHERE id = ?"); $st->execute([$id]); return $st->fetch() ?: null;
    };

    if (is_post()) {
        csrf_require();
        $op = post('op');
        if ($op === 'delete') {
            $row = $load((int)post('id'));
            if ($row) {
                try {
                    db()->prepare("DELETE FROM `$table` WHERE id = ?")->execute([$row['id']]);
                    foreach ($fields as $f) { if (($f['type'] ?? '') === 'image') { delete_upload($row[$f['name']] ?? null); } }
                    flash_set('success', $cfg['singular'] . ' deleted.');
                } catch (PDOException $ex) {
                    flash_set('danger', 'Cannot delete this ' . strtolower($cfg['singular']) . ' because it is used by other records (for example matches).');
                }
            }
            redirect('admin/' . $self);
        }
        if ($op === 'save') {
            $existing = ((int)post('id')) ? $load((int)post('id')) : null;
            [$data, $errors, $uploaded] = crud_collect($fields, $existing ?? []);
            if (!$errors && !empty($cfg['before_save'])) {
                $msg = $cfg['before_save']($data, $existing);
                if ($msg) { $errors[] = $msg; }
            }
            if ($errors) {
                foreach ($uploaded as $u) { delete_upload($u); }
                $action = $existing ? 'edit' : 'new';
                $id = (int)($existing['id'] ?? 0);
                $existing = array_merge($existing ?? [], $_POST, $data);
            } else {
                try {
                    $saveMsg = null;
                    if (!empty($cfg['save_override'])) {
                        $res = $cfg['save_override']($data, $existing);
                        $newId = (int)$res['id']; $saveMsg = $res['msg'] ?? null;
                    } elseif ($existing) {
                        $set = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
                        db()->prepare("UPDATE `$table` SET $set WHERE id = ?")->execute([...array_values($data), $existing['id']]);
                        foreach ($fields as $f) {
                            if (($f['type'] ?? '') === 'image' && !empty($data[$f['name']]) && !empty($existing[$f['name']])) { delete_upload($existing[$f['name']]); }
                        }
                        $newId = (int)$existing['id'];
                    } else {
                        $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
                        db()->prepare("INSERT INTO `$table` ($cols) VALUES (" . implode(',', array_fill(0, count($data), '?')) . ')')->execute(array_values($data));
                        $newId = (int)db()->lastInsertId();
                    }
                    if (!empty($cfg['after_save'])) { $cfg['after_save']($newId, $data, !$existing); }
                    flash_set('success', $saveMsg ?? ($cfg['singular'] . ' saved.'));
                    redirect('admin/' . $self);
                } catch (PDOException $ex) {
                    foreach ($uploaded as $u) { delete_upload($u); }
                    $errors[] = ($ex->getCode() === '23000') ? 'This ' . strtolower($cfg['singular']) . ' already exists (duplicate name) or a linked record is invalid.' : 'Database error while saving.';
                    $action = $existing ? 'edit' : 'new';
                    $id = (int)($existing['id'] ?? 0);
                    $existing = array_merge($existing ?? [], $_POST);
                }
            }
        }
    }

    if ($action === 'edit' && $id && $existing === null) {
        $existing = $load($id);
        if (!$existing) { flash_set('danger', 'Record not found.'); redirect('admin/' . $self); }
    }

    $pageTitle = $cfg['title'];
    require ROOT_PATH . '/includes/admin_header.php';

    if ($action === 'new' || $action === 'edit') {
        echo '<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="page-title h3">' . e($action === 'new' ? 'Add ' . $cfg['singular'] : 'Edit ' . $cfg['singular']) . '</h1>'
           . '<a class="btn btn-outline-theme" href="' . e(url('admin/' . $self)) . '"><i class="bi bi-arrow-left"></i> Back</a></div>';
        foreach ($errors as $er) { echo '<div class="alert alert-danger py-2">' . e($er) . '</div>'; }
        echo '<form method="post" enctype="multipart/form-data" class="ft-card p-4">' . csrf_field()
           . '<input type="hidden" name="op" value="save"><input type="hidden" name="id" value="' . (int)($existing['id'] ?? 0) . '">'
           . crud_render_fields($fields, $existing ?? [])
           . '<div class="mt-4 d-flex gap-2"><button class="btn btn-accent" type="submit"><i class="bi bi-check-lg"></i> Save</button>'
           . '<a class="btn btn-outline-theme" href="' . e(url('admin/' . $self)) . '">Cancel</a></div></form>';
    } else {
        $q = get_str('q'); $page = max(1, (int)($_GET['page'] ?? 1)); $per = 25;
        $sel = $cfg['select'] ?? "SELECT t.* FROM `$table` t";
        $conds = []; $params = [];
        if (!empty($cfg['scope'])) { $conds[] = '(' . $cfg['scope']() . ')'; }
        if ($q !== '' && !empty($cfg['search'])) {
            $conds[] = '(' . implode(' OR ', array_map(fn($c) => "$c LIKE ?", $cfg['search'])) . ')';
            $params = array_fill(0, count($cfg['search']), '%' . $q . '%');
        }
        $where = $conds ? ' WHERE ' . implode(' AND ', $conds) : '';
        $cnt = db()->prepare(preg_replace('/^\\s*SELECT .*? FROM /is', 'SELECT COUNT(*) FROM ', $sel, 1) . $where);
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();
        $st = db()->prepare($sel . $where . ' ORDER BY ' . ($cfg['order'] ?? 't.id DESC') . ' LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per));
        $st->execute($params);
        $rows = $st->fetchAll();

        echo '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h1 class="page-title h3 mb-0"><i class="bi ' . e($cfg['icon'] ?? 'bi-list') . '"></i> ' . e($cfg['title']) . '</h1>'
           . (!empty($cfg['intro']) ? '<p class="text-muted-ft mb-0">' . e($cfg['intro']) . '</p>' : '') . '</div>'
           . '<a class="btn btn-accent" href="' . e(url('admin/' . $self . '?action=new')) . '"><i class="bi bi-plus-lg"></i> Add ' . e($cfg['singular']) . '</a></div>';
        if (!empty($cfg['before_list'])) { ($cfg['before_list'])(); }
        if (!empty($cfg['search'])) {
            echo '<form class="row g-2 mb-3" method="get"><div class="col-sm-6 col-md-4"><input class="form-control" name="q" value="' . e($q) . '" placeholder="Search..."></div>'
               . '<div class="col-auto"><button class="btn btn-outline-theme"><i class="bi bi-search"></i></button></div></form>';
        }
        echo '<div class="ft-card p-0"><div class="table-responsive"><table class="table ft-table table-hover align-middle mb-0"><thead><tr>';
        foreach ($cfg['columns'] as $c) { echo '<th>' . e($c['label']) . '</th>'; }
        echo '<th class="text-end">Actions</th></tr></thead><tbody>';
        if (!$rows) { echo '<tr><td colspan="' . (count($cfg['columns']) + 1) . '" class="text-center text-muted-ft py-4">Nothing here yet.</td></tr>'; }
        foreach ($rows as $r) {
            echo '<tr>';
            foreach ($cfg['columns'] as $c) {
                $v = is_callable($c['key']) ? $c['key']($r) : ($r[$c['key']] ?? '');
                echo '<td class="' . e($c['class'] ?? '') . '">' . (!empty($c['html']) ? $v : e($v)) . '</td>';
            }
            echo '<td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-theme" href="' . e(url('admin/' . $self . '?action=edit&id=' . $r['id'])) . '"><i class="bi bi-pencil"></i></a> '
               . '<form method="post" class="d-inline" data-confirm="' . e($cfg['delete_confirm'] ?? 'Delete this ' . strtolower($cfg['singular']) . '?') . '">' . csrf_field() . (!empty($cfg['force_delete']) ? '<input type="hidden" name="force" value="1">' : '')
               . '<input type="hidden" name="op" value="delete"><input type="hidden" name="id" value="' . (int)$r['id'] . '">'
               . '<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>';
        }
        echo '</tbody></table></div></div>';
        echo pagination($total, $per, $page, $self . '?' . http_build_query(array_filter(['q' => $q])));
    }
    $extraScripts = $cfg['scripts'] ?? '';
    require ROOT_PATH . '/includes/admin_footer.php';
}

/** Bootstrap pagination links. $base is a relative admin/public url that may already contain '?'. */
function pagination(int $total, int $per, int $page, string $base): string
{
    $pages = (int)ceil($total / $per);
    if ($pages <= 1) { return ''; }
    $sep = str_contains($base, '?') ? '&' : '?';
    $link = fn(int $p, string $label, bool $active = false, bool $disabled = false) =>
        '<li class="page-item' . ($active ? ' active' : '') . ($disabled ? ' disabled' : '') . '"><a class="page-link" href="'
        . e(($base[0] === '/' ? '' : '') . $base . $sep . 'page=' . $p) . '">' . $label . '</a></li>';
    $h = '<nav class="mt-3"><ul class="pagination pagination-sm flex-wrap">' . $link(max(1, $page - 1), '&laquo;', false, $page <= 1);
    for ($p = 1; $p <= $pages; $p++) {
        if ($p === 1 || $p === $pages || abs($p - $page) <= 2) { $h .= $link($p, (string)$p, $p === $page); }
        elseif (abs($p - $page) === 3) { $h .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>'; }
    }
    return $h . $link(min($pages, $page + 1), '&raquo;', false, $page >= $pages) . '</ul></nav>';
}
