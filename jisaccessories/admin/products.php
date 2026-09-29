<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/currency.php';

requireAdmin();
$pageTitle = 'Products';
$activeNav = 'products';
$errors = [];
$successMsg = '';

$categories = $conn->query('SELECT * FROM categories ORDER BY name')->fetch_all(MYSQLI_ASSOC);

// ── Upload a single image file ────────────────────────────
function handleImageUpload(array $file): ?string {
    if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed) || $file['size'] > 4 * 1024 * 1024) return null;
    $newName = 'product_' . uniqid() . '.' . $ext;
    $destDir = __DIR__ . '/uploads/';
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    if (move_uploaded_file($file['tmp_name'], $destDir . $newName)) {
        return BASE_PATH . '/admin/uploads/' . $newName;
    }
    return null;
}

// Re-index a multi-file $_FILES sub-array into per-file arrays
function reindexFiles(array $filesField): array {
    $out = [];
    if (!isset($filesField['name']) || !is_array($filesField['name'])) return $out;
    foreach ($filesField['name'] as $i => $name) {
        $out[] = [
            'name'     => $name,
            'type'     => $filesField['type'][$i],
            'tmp_name' => $filesField['tmp_name'][$i],
            'error'    => $filesField['error'][$i],
            'size'     => $filesField['size'][$i],
        ];
    }
    return $out;
}

// ── Create / Update ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'product') {
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $oldPrice = trim($_POST['old_price'] ?? '') !== '' ? (float) $_POST['old_price'] : null;
    $badge = in_array($_POST['badge'] ?? '', ['NEW', 'HOT', 'SALE']) ? $_POST['badge'] : '';
    $stock = (int) ($_POST['stock'] ?? 0);
    $featured = isset($_POST['featured']) ? 1 : 0;
    $imageUrlInput = trim($_POST['image_url'] ?? '');
    // IDs of gallery images the admin wants to remove (edit mode)
    $removeImageIds = array_map('intval', (array)($_POST['remove_image_ids'] ?? []));

    if ($name === '' || $categoryId <= 0 || $price <= 0) {
        $errors[] = 'Name, category, and a valid price are required.';
    } else {
        $slug = slugify($name);
        // Collect all uploaded files (up to 7 total)
        $uploadedFiles = reindexFiles($_FILES['image_files'] ?? []);
        $uploadedUrls = [];
        foreach (array_slice($uploadedFiles, 0, 7) as $file) {
            $url = handleImageUpload($file);
            if ($url) $uploadedUrls[] = $url;
        }

        if ($id > 0) {
            // Delete removed gallery images
            if (!empty($removeImageIds)) {
                $inPlaces = implode(',', array_fill(0, count($removeImageIds), '?'));
                $types = str_repeat('i', count($removeImageIds));
                // Remove from product_images only if they belong to this product
                $delStmt = $conn->prepare("DELETE FROM product_images WHERE id IN ($inPlaces) AND product_id = ?");
                $allParams = array_merge($removeImageIds, [$id]);
                $allTypes = $types . 'i';
                $delStmt->bind_param($allTypes, ...$allParams);
                $delStmt->execute();
                $delStmt->close();
            }

            // Determine primary image
            $primaryImage = $imageUrlInput;
            if (empty($primaryImage) && !empty($uploadedUrls)) {
                $primaryImage = $uploadedUrls[0];
            }
            if (empty($primaryImage)) {
                // Keep existing
                $s = $conn->prepare('SELECT image_url FROM products WHERE id = ?');
                $s->bind_param('i', $id);
                $s->execute();
                $primaryImage = $s->get_result()->fetch_assoc()['image_url'] ?? '';
                $s->close();
            }

            $stmt = $conn->prepare('UPDATE products SET category_id=?, name=?, slug=?, description=?, price=?, old_price=?, image_url=?, badge=?, stock=?, featured=? WHERE id=?');
            $stmt->bind_param('isssddssiii', $categoryId, $name, $slug, $description, $price, $oldPrice, $primaryImage, $badge, $stock, $featured, $id);
            $stmt->execute();
            $stmt->close();

            // Add new gallery images
            if (!empty($uploadedUrls)) {
                $sortStmt = $conn->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM product_images WHERE product_id=?');
                $sortStmt->bind_param('i', $id);
                $sortStmt->execute();
                $nextSort = (int)$sortStmt->get_result()->fetch_row()[0];
                $sortStmt->close();
                $imgStmt = $conn->prepare('INSERT INTO product_images (product_id, image_url, sort_order) VALUES (?,?,?)');
                foreach ($uploadedUrls as $url) {
                    $imgStmt->bind_param('isi', $id, $url, $nextSort);
                    $imgStmt->execute();
                    $nextSort++;
                }
                $imgStmt->close();
            }
            $successMsg = 'Product updated.';
        } else {
            // Ensure slug uniqueness
            $baseSlug = $slug; $si = 1;
            while (true) {
                $chk = $conn->prepare('SELECT id FROM products WHERE slug = ?');
                $chk->bind_param('s', $slug);
                $chk->execute();
                if ($chk->get_result()->num_rows === 0) { $chk->close(); break; }
                $chk->close();
                $slug = $baseSlug . '-' . (++$si);
            }
            $primaryImage = !empty($uploadedUrls) ? $uploadedUrls[0] : $imageUrlInput;
            $stmt = $conn->prepare('INSERT INTO products (category_id, name, slug, description, price, old_price, image_url, badge, stock, featured) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $stmt->bind_param('isssddssii', $categoryId, $name, $slug, $description, $price, $oldPrice, $primaryImage, $badge, $stock, $featured);
            $stmt->execute();
            $newProductId = $stmt->insert_id;
            $stmt->close();

            // Insert all uploaded images into gallery
            if (!empty($uploadedUrls)) {
                $imgStmt = $conn->prepare('INSERT INTO product_images (product_id, image_url, sort_order) VALUES (?,?,?)');
                foreach ($uploadedUrls as $idx => $url) {
                    $imgStmt->bind_param('isi', $newProductId, $url, $idx);
                    $imgStmt->execute();
                }
                $imgStmt->close();
            }
            $successMsg = 'Product created.';
        }
    }
}

// ── Delete ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'delete_product') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM products WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $successMsg = 'Product deleted.';
}

// ── List with search + pagination ────────────────────────
$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$where = '1=1';
$params = [];
$types = '';
if ($search !== '') {
    $where = 'p.name LIKE ?';
    $types = 's';
    $params[] = '%' . $search . '%';
}
$totalCount = 0;
$stmt = $conn->prepare("SELECT COUNT(*) t FROM products p WHERE $where");
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$totalCount = (int) $stmt->get_result()->fetch_assoc()['t'];
$stmt->close();
$totalPages = max(1, (int) ceil($totalCount / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE $where ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$typesLimit = $types . 'ii';
$paramsLimit = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($typesLimit, ...$paramsLimit);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch gallery images for listed products
$galleryMap = [];
if (!empty($products)) {
    $pids = implode(',', array_map(fn($p) => (int)$p['id'], $products));
    $gRes = $conn->query("SELECT * FROM product_images WHERE product_id IN ($pids) ORDER BY sort_order");
    while ($g = $gRes->fetch_assoc()) {
        $galleryMap[$g['product_id']][] = $g;
    }
}

require_once __DIR__ . '/layout.php';
?>

<?php if ($successMsg): ?><div class="alert alert-success"><?= e($successMsg) ?></div><?php endif; ?>
<?php if (!empty($errors)): ?><div class="alert alert-error"><?php foreach ($errors as $err) echo '<div>' . e($err) . '</div>'; ?></div><?php endif; ?>

<div class="panel">
  <div class="panel-head">
    <h3>All Products (<?= $totalCount ?>)</h3>
    <div style="display:flex;gap:10px;">
      <form method="GET" style="display:flex;gap:8px;">
        <input type="text" name="search" placeholder="Search products…" value="<?= e($search) ?>" style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:8px 12px;color:var(--text);font-size:13px;">
        <button class="btn btn-outline btn-sm">Search</button>
      </form>
      <button class="btn btn-primary btn-sm" onclick="openCreateModal()">+ Add Product</button>
    </div>
  </div>
  <div class="table-scroll">
    <table class="data-table">
      <thead><tr><th>Product</th><th>Gallery</th><th>Category</th><th>Price</th><th>Stock</th><th>Badge</th><th>Featured</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($products)): ?><tr><td colspan="8" style="text-align:center;color:var(--text2);padding:30px;">No products found.</td></tr><?php endif; ?>
        <?php foreach ($products as $p): ?>
        <tr>
          <td><div class="prod-thumb-row"><img src="<?= e($p['image_url']) ?>" alt=""><span><?= e($p['name']) ?></span></div></td>
          <td>
            <div style="display:flex;gap:4px;flex-wrap:wrap;">
              <?php $imgs = $galleryMap[$p['id']] ?? []; ?>
              <?php foreach (array_slice($imgs, 0, 4) as $img): ?>
                <img src="<?= e($img['image_url']) ?>" style="width:32px;height:32px;object-fit:cover;border-radius:4px;border:1px solid var(--border);" alt="">
              <?php endforeach; ?>
              <?php if (count($imgs) > 4): ?><span style="font-size:11px;color:var(--text2);align-self:center;">+<?= count($imgs)-4 ?> more</span><?php endif; ?>
              <?php if (empty($imgs)): ?><span style="font-size:11px;color:var(--text2);">—</span><?php endif; ?>
            </div>
          </td>
          <td><?= e($p['category_name']) ?></td>
          <td><?= formatPrice((float)$p['price']) ?></td>
          <td><?= (int)$p['stock'] ?></td>
          <td><?= $p['badge'] ? '<span class="status-badge status-pending">' . e($p['badge']) . '</span>' : '—' ?></td>
          <td><?= $p['featured'] ? '⭐' : '—' ?></td>
          <td>
            <div class="action-icons">
              <button class="icon-btn" title="Edit" onclick='openEditModal(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= json_encode($galleryMap[$p['id']] ?? [], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>✏️</button>
              <form method="POST" data-confirm="Delete this product permanently?">
                <input type="hidden" name="form" value="delete_product">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button class="icon-btn danger" title="Delete">🗑️</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Product Modal -->
<div class="modal-overlay" id="productModal">
  <div class="modal-box" style="max-width:720px;">
    <div class="modal-head">
      <h3 id="modalTitle">Add Product</h3>
      <button class="modal-close" data-close-modal>&times;</button>
    </div>
    <form method="POST" enctype="multipart/form-data" id="productForm">
      <input type="hidden" name="form" value="product">
      <input type="hidden" name="id" id="f_id" value="0">
      <div class="form-group"><label>Product Name</label><input type="text" name="name" id="f_name" required></div>
      <div class="form-grid">
        <div class="form-group">
          <label>Category</label>
          <select name="category_id" id="f_category_id" required>
            <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Badge</label>
          <select name="badge" id="f_badge">
            <option value="">None</option><option value="NEW">NEW</option><option value="HOT">HOT</option><option value="SALE">SALE</option>
          </select>
        </div>
      </div>
      <div class="form-group full"><label>Description</label><textarea name="description" id="f_description" rows="3"></textarea></div>
      <div class="form-grid">
        <div class="form-group"><label>Price (₹)</label><input type="number" step="0.01" name="price" id="f_price" required></div>
        <div class="form-group"><label>Old Price (₹, optional)</label><input type="number" step="0.01" name="old_price" id="f_old_price"></div>
        <div class="form-group"><label>Stock</label><input type="number" name="stock" id="f_stock" value="100"></div>
        <div class="form-group"><label class="checkbox-row" style="margin-top:12px;"><input type="checkbox" name="featured" id="f_featured"> Featured product</label></div>
      </div>
      <div class="form-group"><label>Primary Image URL (optional — will be overridden by first uploaded file)</label><input type="text" name="image_url" id="f_image_url" placeholder="https://images.unsplash.com/…"></div>

      <!-- Multi-image upload -->
      <div class="form-group">
        <label>Upload Images <small style="color:var(--text2);">(up to 7 · JPG/PNG/WebP · max 4 MB each)</small></label>
        <div id="imgDropzone" class="img-dropzone" onclick="document.getElementById('imgFileInput').click()">
          <div class="dropzone-inner">
            <span class="dropzone-icon">🖼️</span>
            <span>Click to select images or drag &amp; drop here</span>
            <span style="font-size:12px;color:var(--text2);">Max 7 images</span>
          </div>
        </div>
        <input type="file" name="image_files[]" id="imgFileInput" accept="image/*" multiple style="display:none;">
      </div>

      <!-- Gallery preview (new uploads) -->
      <div id="newImgPreviewGrid" class="img-preview-grid" style="display:none;"></div>

      <!-- Existing gallery (edit mode) -->
      <div id="existingGallerySection" style="display:none;">
        <label style="font-size:13px;font-weight:600;color:var(--text2);margin-bottom:8px;display:block;">Current Gallery Images (click ✕ to remove)</label>
        <div id="existingGalleryGrid" class="img-preview-grid"></div>
      </div>

      <div style="display:flex;gap:10px;margin-top:20px;">
        <button type="submit" class="btn btn-primary">Save Product</button>
        <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
      </div>
    </form>
  </div>
</div>

<style>
.img-dropzone {
  border: 2px dashed var(--border);
  border-radius: 12px;
  padding: 24px;
  text-align: center;
  cursor: pointer;
  transition: border-color .2s, background .2s;
  background: var(--bg);
}
.img-dropzone:hover, .img-dropzone.drag-over {
  border-color: var(--accent);
  background: color-mix(in srgb, var(--accent) 5%, transparent);
}
.dropzone-inner { display:flex; flex-direction:column; gap:6px; align-items:center; pointer-events:none; }
.dropzone-icon  { font-size:28px; }
.img-preview-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
  gap: 10px;
  margin-top: 10px;
}
.img-preview-item {
  position: relative;
  border-radius: 8px;
  overflow: hidden;
  aspect-ratio: 1;
  border: 2px solid var(--border);
  background: var(--bg);
}
.img-preview-item img {
  width: 100%; height: 100%; object-fit: cover; display: block;
}
.img-preview-item .remove-btn {
  position: absolute; top: 3px; right: 3px;
  background: rgba(0,0,0,.65); color: #fff;
  border: none; border-radius: 50%; width: 20px; height: 20px;
  font-size: 11px; cursor: pointer; display: flex; align-items: center; justify-content: center;
  line-height: 1;
}
.img-preview-item .remove-btn:hover { background: #e74c3c; }
.img-preview-item .primary-badge {
  position: absolute; bottom: 3px; left: 3px;
  background: var(--accent); color: #fff;
  font-size: 9px; border-radius: 4px; padding: 1px 5px; font-weight: 700;
}
</style>

<script>
const MAX_IMAGES = 7;
let newFileList = []; // DataTransfer-based file list

const imgInput    = document.getElementById('imgFileInput');
const dropzone    = document.getElementById('imgDropzone');
const newGrid     = document.getElementById('newImgPreviewGrid');
const existSec    = document.getElementById('existingGallerySection');
const existGrid   = document.getElementById('existingGalleryGrid');

// Drag & drop
dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('drag-over'); });
dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag-over'));
dropzone.addEventListener('drop', e => {
  e.preventDefault(); dropzone.classList.remove('drag-over');
  handleFileSelection(Array.from(e.dataTransfer.files));
});

imgInput.addEventListener('change', () => {
  handleFileSelection(Array.from(imgInput.files));
  imgInput.value = '';
});

function countExistingImages() {
  return existGrid.querySelectorAll('.img-preview-item:not(.marked-remove)').length;
}

function handleFileSelection(files) {
  const slots = MAX_IMAGES - countExistingImages() - newFileList.length;
  if (slots <= 0) { alert('Maximum 7 images allowed. Remove some existing images first.'); return; }
  const toAdd = files.filter(f => f.type.startsWith('image/')).slice(0, slots);
  if (toAdd.length < files.length) alert(`Only ${slots} more image(s) can be added (max 7 total).`);
  toAdd.forEach(addNewFilePreview);
  syncFileInput();
}

function addNewFilePreview(file) {
  newFileList.push(file);
  const idx = newFileList.length - 1;
  const reader = new FileReader();
  reader.onload = e => {
    const item = document.createElement('div');
    item.className = 'img-preview-item';
    item.dataset.idx = idx;
    item.innerHTML = `<img src="${e.target.result}" alt=""><button type="button" class="remove-btn" onclick="removeNewFile(${idx})">✕</button>${idx === 0 && countExistingImages() === 0 ? '<span class="primary-badge">Primary</span>' : ''}`;
    newGrid.appendChild(item);
    newGrid.style.display = 'grid';
  };
  reader.readAsDataURL(file);
}

function removeNewFile(idx) {
  newFileList[idx] = null;
  const item = newGrid.querySelector(`[data-idx="${idx}"]`);
  if (item) item.remove();
  if (!newGrid.querySelector('.img-preview-item')) newGrid.style.display = 'none';
  syncFileInput();
}

function syncFileInput() {
  const dt = new DataTransfer();
  newFileList.filter(Boolean).forEach(f => dt.items.add(f));
  imgInput.files = dt.files;
}

function renderExistingGallery(images) {
  existGrid.innerHTML = '';
  if (!images || images.length === 0) { existSec.style.display = 'none'; return; }
  existSec.style.display = 'block';
  images.forEach((img, i) => {
    const item = document.createElement('div');
    item.className = 'img-preview-item';
    item.innerHTML = `
      <img src="${img.image_url}" alt="">
      <button type="button" class="remove-btn" onclick="markRemoveExisting(this, ${img.id})">✕</button>
      <input type="hidden" name="" class="remove-id-input" value="">
      ${i === 0 ? '<span class="primary-badge">Primary</span>' : ''}
    `;
    existGrid.appendChild(item);
  });
}

function markRemoveExisting(btn, imgId) {
  const item = btn.closest('.img-preview-item');
  item.classList.add('marked-remove');
  item.style.opacity = '.35';
  btn.textContent = '↩';
  btn.onclick = () => unmarkRemoveExisting(btn, imgId, item);
  // Add hidden input for removal
  const inp = document.createElement('input');
  inp.type = 'hidden';
  inp.name = 'remove_image_ids[]';
  inp.value = imgId;
  inp.id = 'rmv_' + imgId;
  document.getElementById('productForm').appendChild(inp);
}

function unmarkRemoveExisting(btn, imgId, item) {
  item.classList.remove('marked-remove');
  item.style.opacity = '';
  btn.textContent = '✕';
  btn.onclick = () => markRemoveExisting(btn, imgId);
  const inp = document.getElementById('rmv_' + imgId);
  if (inp) inp.remove();
}

function openCreateModal() {
  document.getElementById('modalTitle').textContent = 'Add Product';
  document.getElementById('productForm').reset();
  document.getElementById('f_id').value = 0;
  newFileList = [];
  newGrid.innerHTML = ''; newGrid.style.display = 'none';
  existSec.style.display = 'none'; existGrid.innerHTML = '';
  // Remove any stale remove-id inputs
  document.querySelectorAll('input[name="remove_image_ids[]"]').forEach(e => e.remove());
  document.getElementById('productModal').classList.add('open');
}

function openEditModal(p, gallery) {
  document.getElementById('modalTitle').textContent = 'Edit Product';
  document.getElementById('f_id').value = p.id;
  document.getElementById('f_name').value = p.name;
  document.getElementById('f_category_id').value = p.category_id;
  document.getElementById('f_badge').value = p.badge;
  document.getElementById('f_description').value = p.description || '';
  document.getElementById('f_price').value = p.price;
  document.getElementById('f_old_price').value = p.old_price || '';
  document.getElementById('f_stock').value = p.stock;
  document.getElementById('f_featured').checked = !!parseInt(p.featured);
  document.getElementById('f_image_url').value = p.image_url;
  newFileList = [];
  newGrid.innerHTML = ''; newGrid.style.display = 'none';
  document.querySelectorAll('input[name="remove_image_ids[]"]').forEach(e => e.remove());
  renderExistingGallery(gallery);
  document.getElementById('productModal').classList.add('open');
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
