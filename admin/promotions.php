<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
ensure_installed();
require_admin();

$types = promotion_type_labels();
$action = (string) ($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if ($action === 'delete') {
        db()->prepare('DELETE FROM promotions WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
        flash('ok', 'Акция удалена.');
        redirect('admin/promotions.php');
    }

    if ($action === 'toggle') {
        db()->prepare('UPDATE promotions SET published = IF(published=1,0,1) WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
        flash('ok', 'Статус публикации изменён.');
        redirect('admin/promotions.php');
    }

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $productId = (int) ($_POST['product_id'] ?? 0);
        $type = (string) ($_POST['type'] ?? 'day');
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $startDate = trim((string) ($_POST['start_date'] ?? ''));
        $endDate = trim((string) ($_POST['end_date'] ?? ''));
        $promoPriceRaw = trim((string) ($_POST['promo_price'] ?? ''));
        $promoPrice = $promoPriceRaw === '' ? null : (float) $promoPriceRaw;
        $published = !empty($_POST['published']) ? 1 : 0;

        if (!isset($types[$type])) {
            $type = 'day';
        }

        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);
        if (!$start || $start->format('Y-m-d') !== $startDate || !$end || $end->format('Y-m-d') !== $endDate) {
            flash('error', 'Укажите корректные даты начала и окончания акции.');
            redirect('admin/promotions.php' . ($id ? '?id=' . $id : ''));
        }
        if ($end < $start) {
            flash('error', 'Дата окончания не может быть раньше даты начала.');
            redirect('admin/promotions.php' . ($id ? '?id=' . $id : ''));
        }

        $productStmt = db()->prepare('SELECT id,name,price FROM products WHERE id = ?');
        $productStmt->execute([$productId]);
        $selectedProduct = $productStmt->fetch();
        if (!$selectedProduct || $title === '') {
            flash('error', 'Выберите товар и заполните название акции.');
            redirect('admin/promotions.php' . ($id ? '?id=' . $id : ''));
        }

        if ($promoPrice !== null && $promoPrice <= 0) {
            flash('error', 'Специальная цена должна быть больше нуля.');
            redirect('admin/promotions.php' . ($id ? '?id=' . $id : ''));
        }
        if ($promoPrice !== null && $promoPrice >= (float) $selectedProduct['price']) {
            flash('error', 'Акционная цена должна быть ниже обычной цены товара.');
            redirect('admin/promotions.php' . ($id ? '?id=' . $id : ''));
        }

        $oldImage = '';
        if ($id > 0) {
            $st = db()->prepare('SELECT image FROM promotions WHERE id = ?');
            $st->execute([$id]);
            $oldImage = (string) ($st->fetchColumn() ?: '');
        }
        $image = handle_promotion_upload($oldImage);

        if ($id > 0) {
            $st = db()->prepare('UPDATE promotions SET product_id=?,type=?,title=?,description=?,image=?,promo_price=?,start_date=?,end_date=?,published=? WHERE id=?');
            $st->execute([$productId, $type, $title, $description, $image, $promoPrice, $startDate, $endDate, $published, $id]);
        } else {
            $st = db()->prepare('INSERT INTO promotions (product_id,type,title,description,image,promo_price,start_date,end_date,published) VALUES (?,?,?,?,?,?,?,?,?)');
            $st->execute([$productId, $type, $title, $description, $image, $promoPrice, $startDate, $endDate, $published]);
        }

        flash('ok', 'Акция сохранена. Период: ' . date('d.m.Y', strtotime($startDate)) . ' — ' . date('d.m.Y', strtotime($endDate)) . '.');
        redirect('admin/promotions.php');
    }
}

$products = db()->query('SELECT id,name,price,image,slug,visual FROM products ORDER BY name')->fetchAll();
$list = db()->query('SELECT a.*, p.name AS product_name, p.price AS product_price, p.image AS product_image, p.visual AS product_visual, p.slug AS product_slug FROM promotions a JOIN products p ON p.id=a.product_id ORDER BY a.id DESC')->fetchAll();
$edit = null;
if (!empty($_GET['id'])) {
    $st = db()->prepare('SELECT * FROM promotions WHERE id = ?');
    $st->execute([(int) $_GET['id']]);
    $edit = $st->fetch() ?: null;
}

$defaultType = (string) ($edit['type'] ?? 'day');
$defaultStart = (string) ($edit['start_date'] ?? date('Y-m-d'));
$defaultEnd = (string) ($edit['end_date'] ?? promotion_end_date($defaultType, $defaultStart));
$previewImage = '';
if ($edit) {
    $previewStmt = db()->prepare('SELECT a.*, p.image AS product_image, p.visual AS product_visual, p.slug AS product_slug FROM promotions a JOIN products p ON p.id=a.product_id WHERE a.id=?');
    $previewStmt->execute([(int) $edit['id']]);
    $previewRow = $previewStmt->fetch();
    if ($previewRow) {
        $previewImage = promotion_image_src($previewRow);
    }
}

$counts = ['all' => count($list), 'active' => 0, 'scheduled' => 0, 'draft' => 0, 'finished' => 0];
foreach ($list as $promo) {
    $status = promotion_status($promo);
    if ($status === 'Активна') $counts['active']++;
    elseif ($status === 'Запланирована') $counts['scheduled']++;
    elseif ($status === 'Черновик') $counts['draft']++;
    elseif ($status === 'Завершена') $counts['finished']++;
}

$statusKeys = [
    'Активна' => 'active',
    'Запланирована' => 'scheduled',
    'Черновик' => 'draft',
    'Завершена' => 'finished',
];

$adminTitle = 'Акции';
require __DIR__ . '/_header.php';
?>
<div class="promo-admin-head">
    <div>
        <h1>Управление акциями</h1>
        <p class="muted">Создавайте предложения дня, недели и месяца. Даты начала и окончания можно задавать и изменять вручную.</p>
    </div>
    <div class="promo-admin-head-actions">
        <?php if ($edit): ?><a class="btn ghost" href="<?= e(url('admin/promotions.php')) ?>">+ Новая акция</a><?php endif; ?>
        <a class="btn" href="<?= e(url('promotions.php')) ?>" target="_blank" rel="noopener">Посмотреть на сайте</a>
    </div>
</div>

<div class="promo-admin-stats" aria-label="Статистика акций">
    <div><strong><?= (int) $counts['all'] ?></strong><span>Всего</span></div>
    <div><strong><?= (int) $counts['active'] ?></strong><span>Активные</span></div>
    <div><strong><?= (int) $counts['scheduled'] ?></strong><span>Запланированы</span></div>
    <div><strong><?= (int) $counts['draft'] ?></strong><span>Черновики</span></div>
</div>

<div class="promo-admin-layout promo-admin-layout-v2">
    <form method="post" enctype="multipart/form-data" class="box promo-admin-form promo-admin-form-v2" data-promo-form>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">

        <div class="promo-form-title-row">
            <div>
                <p class="promo-form-step">Настройка предложения</p>
                <h2><?= $edit ? 'Редактирование акции' : 'Новая акция' ?></h2>
            </div>
            <?php if ($edit): ?><span class="promo-edit-id">ID <?= (int) $edit['id'] ?></span><?php endif; ?>
        </div>

        <section class="promo-form-section">
            <div class="promo-form-section-head">
                <span>1</span>
                <div><h3>Тип и товар</h3><p>Выберите тип акции и товар из каталога.</p></div>
            </div>

            <fieldset class="promo-type-picker">
                <legend class="sr-only">Тип акции</legend>
                <?php foreach ($types as $key => $label): ?>
                    <label class="promo-type-option">
                        <input type="radio" name="type" value="<?= e($key) ?>" data-promo-type data-duration="<?= promotion_duration_days($key) ?>" <?= $defaultType === $key ? 'checked' : '' ?>>
                        <span class="promo-type-card promo-type-card-<?= e($key) ?>">
                            <span class="promo-type-dot"></span>
                            <b><?= e($label) ?></b>
                        </span>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <label for="promo-product">Товар</label>
            <select id="promo-product" name="product_id" required data-promo-product>
                <option value="">Выберите товар</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= (int) $product['id'] ?>"
                            data-price="<?= e((string) $product['price']) ?>"
                            data-image="<?= e(product_image_src($product)) ?>"
                            <?= (int) ($edit['product_id'] ?? 0) === (int) $product['id'] ? 'selected' : '' ?>><?= e($product['name']) ?> — <?= format_price((float) $product['price']) ?></option>
                <?php endforeach; ?>
            </select>
        </section>

        <section class="promo-form-section">
            <div class="promo-form-section-head">
                <span>2</span>
                <div><h3>Описание</h3><p>Название и короткий текст, который увидит посетитель.</p></div>
            </div>

            <label for="promo-title">Название акции</label>
            <input id="promo-title" type="text" name="title" maxlength="180" required value="<?= e($edit['title'] ?? '') ?>" placeholder="Например: iPhone 16 Pro — предложение дня">

            <label for="promo-description">Краткое описание</label>
            <textarea id="promo-description" name="description" rows="4" maxlength="500" placeholder="Коротко опишите выгоду предложения"><?= e($edit['description'] ?? '') ?></textarea>
        </section>

        <section class="promo-form-section">
            <div class="promo-form-section-head">
                <span>3</span>
                <div><h3>Срок действия</h3></div>
            </div>

            <div class="promo-period-grid">
                <div>
                    <label for="promo-start">Начало</label>
                    <input id="promo-start" type="date" name="start_date" required value="<?= e($defaultStart) ?>" data-promo-start>
                </div>
                <div>
                    <label for="promo-end">Окончание</label>
                    <input id="promo-end" type="date" name="end_date" required value="<?= e($defaultEnd) ?>" min="<?= e($defaultStart) ?>" data-promo-end>
                </div>
            </div>
            <div class="promo-period-preview">
                <span class="promo-period-icon">→</span>
                <div>
                    <b data-promo-period-text><?= e(date('d.m.Y', strtotime($defaultStart))) ?> — <?= e(date('d.m.Y', strtotime($defaultEnd))) ?></b>
                    <small data-promo-duration-text><?= e(promotion_period_label(['start_date' => $defaultStart, 'end_date' => $defaultEnd])) ?> включительно</small>
                </div>
            </div>
        </section>

        <section class="promo-form-section">
            <div class="promo-form-section-head">
                <span>4</span>
                <div><h3>Цена и изображение</h3><p>Специальная цена необязательна. Изображение можно оставить от товара.</p></div>
            </div>

            <div class="promo-price-box">
                <div>
                    <label for="promo-price">Специальная цена, ₽</label>
                    <input id="promo-price" type="number" name="promo_price" min="1" step="1" value="<?= e((string) ($edit['promo_price'] ?? '')) ?>" placeholder="Например: 109990" data-promo-price>
                </div>
                <div class="promo-price-hint" data-promo-price-hint>Выберите товар, чтобы увидеть обычную цену.</div>
            </div>

            <div class="promo-image-editor">
                <div class="promo-image-preview<?= $previewImage === '' ? ' is-empty' : '' ?>" data-promo-image-box>
                    <img src="<?= e($previewImage) ?>" alt="Предпросмотр акции" data-promo-image-preview <?= $previewImage === '' ? 'hidden' : '' ?>>
                    <span data-promo-image-empty <?= $previewImage !== '' ? 'hidden' : '' ?>>Фото товара появится здесь</span>
                </div>
                <div class="promo-image-fields">
                    <label for="promo-image-url">Ссылка на изображение</label>
                    <input id="promo-image-url" type="text" name="promo_image_url" value="<?= e($edit['image'] ?? '') ?>" placeholder="https://... или assets/img/..." data-promo-image-url>
                    <label for="promo-image-file">Или загрузите файл</label>
                    <input id="promo-image-file" type="file" name="promo_image_file" accept="image/jpeg,image/png,image/webp,image/gif" data-promo-image-file>
                    <p class="field-help">JPG, PNG, WebP или GIF до 6 МБ. Если ничего не выбрать, используется изображение товара.</p>
                </div>
            </div>
        </section>

        <section class="promo-form-section promo-publish-section">
            <label class="promo-publish-toggle">
                <input type="checkbox" name="published" value="1" <?= !isset($edit['published']) || (int) $edit['published'] === 1 ? 'checked' : '' ?>>
                <span class="promo-toggle-ui"></span>
                <span><b>Опубликовать на сайте</b><small>Акция будет видна посетителям только в пределах срока действия.</small></span>
            </label>
        </section>

        <div class="promo-form-actions promo-form-actions-v2">
            <button class="btn" type="submit"><?= $edit ? 'Сохранить изменения' : 'Создать акцию' ?></button>
            <?php if ($edit): ?><a class="btn ghost" href="<?= e(url('admin/promotions.php')) ?>">Отмена</a><?php endif; ?>
        </div>
    </form>

    <div class="box promo-admin-list promo-admin-list-v2">
        <div class="promo-list-head">
            <div><p class="promo-form-step">Контент сайта</p><h2>Все акции</h2></div>
            <span><?= count($list) ?> шт.</span>
        </div>

        <?php if (!$list): ?>
            <div class="promo-list-empty"><b>Акций пока нет</b><p class="muted">Создайте первую акцию слева — она сразу появится в этом списке.</p></div>
        <?php endif; ?>

        <div class="promo-admin-items">
        <?php foreach ($list as $promo): ?>
            <?php
            $status = promotion_status($promo);
            $statusKey = $statusKeys[$status] ?? 'draft';
            $hasPromoPrice = (float) $promo['promo_price'] > 0;
            $saving = $hasPromoPrice ? max(0, (float) $promo['product_price'] - (float) $promo['promo_price']) : 0;
            ?>
            <article class="promo-admin-item promo-admin-item-v2">
                <a class="promo-admin-thumb" href="<?= e(url('product.php?id=' . (int) $promo['product_id'])) ?>" target="_blank" rel="noopener">
                    <img src="<?= e(promotion_image_src($promo)) ?>" alt="<?= e($promo['title']) ?>">
                    <span class="promo-badge promo-badge-small"><?= e(promotion_type_label((string) $promo['type'])) ?></span>
                </a>
                <div class="promo-admin-item-main">
                    <div class="promo-admin-meta">
                        <span class="promo-state promo-state-<?= e($statusKey) ?>"><?= e($status) ?></span>
                        <span class="promo-duration-chip"><?= e(promotion_period_label($promo)) ?></span>
                    </div>
                    <h3><?= e($promo['title']) ?></h3>
                    <p class="promo-product-line"><?= e($promo['product_name']) ?></p>
                    <div class="promo-list-period">
                        <span>С <?= e(date('d.m.Y', strtotime((string) $promo['start_date']))) ?></span>
                        <i>→</i>
                        <span>по <?= e(date('d.m.Y', strtotime((string) $promo['end_date']))) ?></span>
                    </div>
                    <div class="promo-admin-price-row">
                        <?php if ($hasPromoPrice): ?>
                            <strong><?= format_price((float) $promo['promo_price']) ?></strong>
                            <span class="old"><?= format_price((float) $promo['product_price']) ?></span>
                            <?php if ($saving > 0): ?><em>−<?= format_price($saving) ?></em><?php endif; ?>
                        <?php else: ?>
                            <strong><?= format_price((float) $promo['product_price']) ?></strong>
                            <span class="muted">без специальной цены</span>
                        <?php endif; ?>
                    </div>
                    <div class="promo-admin-actions promo-admin-actions-v2">
                        <a class="promo-action-link" href="<?= e(url('admin/promotions.php?id=' . (int) $promo['id'])) ?>">Редактировать</a>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int) $promo['id'] ?>">
                            <button type="submit" class="link-button"><?= (int) $promo['published'] ? 'Снять с публикации' : 'Опубликовать' ?></button>
                        </form>
                        <form method="post" onsubmit="return confirm('Удалить эту акцию?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $promo['id'] ?>">
                            <button type="submit" class="link-button link-danger">Удалить</button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
