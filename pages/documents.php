<?php
// pages/documents.php

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}
require __DIR__ . '/../includes/db.php';

// Set timezone
date_default_timezone_set('Europe/Bucharest');

$user_id       = $_SESSION['user_id'];
$success       = '';  
$error         = '';
$tab           = $_GET['tab']            ?? 'vehicle';
$filterVehicle = isset($_GET['filter_vehicle']) && $_GET['filter_vehicle'] !== ''
               ? intval($_GET['filter_vehicle'])
               : null;

// 1) Fetch vehicles for dropdown
$stmtV = $conn->prepare("SELECT id, brand, model, year FROM vehicles WHERE user_id = ?");
$stmtV->bind_param("i", $user_id);
$stmtV->execute();
$userVehicles = $stmtV->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtV->close();

// 2) Handle POST actions: delete, add, edit, del_group
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ft = $_POST['form_type'] ?? '';

    // DELETE SINGLE document
    if ($ft === 'del_doc') {
        $doc_id = intval($_POST['doc_id'] ?? 0);
        if ($doc_id) {
            $row = $conn->prepare("SELECT file_path FROM documents WHERE id=? AND user_id=?");
            $row->bind_param("ii", $doc_id, $user_id);
            $row->execute();
            $res = $row->get_result()->fetch_assoc();
            $row->close();

            if ($res) {
                @unlink(__DIR__ . "/../" . $res['file_path']);
                
                $delNotif = $conn->prepare("DELETE FROM notifications WHERE document_id=? AND user_id=?");
                $delNotif->bind_param("ii", $doc_id, $user_id);
                $delNotif->execute();
                $delNotif->close();

                $del = $conn->prepare("DELETE FROM documents WHERE id=? AND user_id=?");
                $del->bind_param("ii", $doc_id, $user_id);
                $del->execute();
                $del->close();
                $success = "Document șters.";
            } else {
                $error = "Document negăsit.";
            }
        }

    // DELETE GROUP of documents
    } elseif ($ft === 'del_group') {
        $doc_ids_raw = $_POST['doc_ids'] ?? '';
        $doc_ids = array_filter(array_map('intval', explode(',', $doc_ids_raw)));

        if (!empty($doc_ids)) {
            $placeholders = implode(',', array_fill(0, count($doc_ids), '?'));
            $types = str_repeat('i', count($doc_ids));

            $stmtPaths = $conn->prepare("SELECT file_path FROM documents WHERE user_id=? AND id IN ($placeholders)");
            $stmtPaths->bind_param("i" . $types, $user_id, ...$doc_ids);
            $stmtPaths->execute();
            $paths = $stmtPaths->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtPaths->close();

            foreach ($paths as $p) {
                @unlink(__DIR__ . "/../" . $p['file_path']);
            }
            
            $delNotifs = $conn->prepare("DELETE FROM notifications WHERE user_id=? AND document_id IN ($placeholders)");
            $delNotifs->bind_param("i" . $types, $user_id, ...$doc_ids);
            $delNotifs->execute();
            $delNotifs->close();

            $delDocs = $conn->prepare("DELETE FROM documents WHERE user_id=? AND id IN ($placeholders)");
            $delDocs->bind_param("i" . $types, $user_id, ...$doc_ids);
            
            if ($delDocs->execute()) {
                $success = "Grupul de documente a fost șters.";
            } else {
                $error = "Eroare la ștergerea grupului: " . $delDocs->error;
            }
            $delDocs->close();

        } else {
            $error = "Nu au fost specificate documente pentru ștergere.";
        }
    
    // ADD document(s)
    } elseif ($ft === 'add_doc') {
        $type_input = $_POST['type'] ?? '';
        $rawVid     = $_POST['vehicle_id'] ?? '';
        $vehicle_id = ($tab === 'vehicle' && $rawVid !== '') ? intval($rawVid) : null;
        $expires_at = $_POST['expires_at'] ?: null;
        $note       = trim($_POST['note'] ?? '');

        if (empty($_FILES['files']['name'][0])) {
            $error = "Nu ai ales niciun fișier.";
        } else {
            foreach ($_FILES['files']['tmp_name'] as $i => $tmpPath) {
                $orig = $_FILES['files']['name'][$i];
                if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) {
                    $error .= "„{$orig}” nu a fost încărcat. ";
                    continue;
                }
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                if (!in_array($ext, ['pdf','jpg','jpeg','png'])) {
                    $error .= "Format nepermis pentru „{$orig}”. ";
                    continue;
                }
                if ($_FILES['files']['size'][$i] > 5*1024*1024) {
                    $error .= "„{$orig}” prea mare. ";
                    continue;
                }
                $dir = __DIR__ . "/../uploads/documents/{$user_id}/";
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $uniq = bin2hex(random_bytes(8)) . ".{$ext}";
                $dst  = $dir . $uniq;
                if (!move_uploaded_file($tmpPath, $dst)) {
                    $error .= "Nu am putut salva „{$orig}”. ";
                    continue;
                }
                $path = "uploads/documents/{$user_id}/{$uniq}";

                if ($vehicle_id !== null) {
                    $sql = "INSERT INTO documents (user_id,vehicle_id,type,file_path,note,expires_at,uploaded_at) VALUES (?,?,?,?,?,?,NOW())";
                    $st = $conn->prepare($sql);
                    $st->bind_param("iissss", $user_id, $vehicle_id, $type_input, $path, $note, $expires_at);
                } else {
                    $sql = "INSERT INTO documents (user_id,type,file_path,note,expires_at,uploaded_at) VALUES (?,?,?,?,?,NOW())";
                    $st = $conn->prepare($sql);
                    $st->bind_param("issss", $user_id, $type_input, $path, $note, $expires_at);
                }

                if (!$st->execute()) {
                    $error .= "Eroare „{$orig}”: " . $st->error;
                    @unlink($dst);
                } else {
                    $success .= "„{$orig}” încărcat cu succes. ";
                    $docId = $st->insert_id;

                    if ($expires_at) {
                        $noteText = "Documentul '{$type_input}' expiră la {$expires_at}.";
                        $notif = $conn->prepare("INSERT INTO notifications (user_id, vehicle_id, type, trigger_date, note, source, document_id) VALUES (?, ?, 'date', ?, ?, 'document', ?)");
                        $notif->bind_param('iissi', $user_id, $vehicle_id, $expires_at, $noteText, $docId);
                        $notif->execute();
                        $notif->close();
                    }
                }
                $st->close();
            }
        }

    // EDIT SINGLE
    } elseif ($ft === 'edit_doc') {
        $doc_id     = intval($_POST['doc_id'] ?? 0);
        $type_new   = $_POST['type']        ?? '';
        $expires_at = $_POST['expires_at'] ?: null;
        $note_new   = trim($_POST['note']   ?? '');
        $rawVid2    = $_POST['vehicle_id']  ?? '';
        $vehicle_n  = ($tab==='vehicle' && $rawVid2!=='') ? intval($rawVid2) : null;

        $replace = null;
        if (!empty($_FILES['new_file']['tmp_name']) && $_FILES['new_file']['error']===UPLOAD_ERR_OK) {
            $orig = $_FILES['new_file']['name'];
            $ext  = strtolower(pathinfo($orig,PATHINFO_EXTENSION));
            if (in_array($ext,['pdf','jpg','jpeg','png']) && $_FILES['new_file']['size']<=5*1024*1024) {
                $dir = __DIR__."/../uploads/documents/{$user_id}/";
                if (!is_dir($dir)) mkdir($dir,0755,true);
                $uniq = bin2hex(random_bytes(8)).".$ext";
                $dst  = $dir.$uniq;
                if (move_uploaded_file($_FILES['new_file']['tmp_name'],$dst)) {
                    $old = $conn->prepare("SELECT file_path FROM documents WHERE id=? AND user_id=?");
                    $old->bind_param("ii",$doc_id,$user_id);
                    $old->execute();
                    $oldp = $old->get_result()->fetch_assoc()['file_path'];
                    @unlink(__DIR__."/../{$oldp}");
                    $old->close();
                    $replace = "uploads/documents/{$user_id}/{$uniq}";
                }
            }
        }

        if (!$type_new) {
            $error = "Alege un tip.";
        } else {
            $sets = ["type=?","note=?","expires_at=?"];
            $types = "sss";
            $params = [$type_new,$note_new,$expires_at];
            if ($vehicle_n!==null) {
                $sets[]="vehicle_id=?";
                $types.="i"; $params[]=$vehicle_n;
            }
            if ($replace) {
                $sets[]="file_path=?";
                $types.="s"; $params[]=$replace;
            }
            $params[] = $doc_id; $types.="i";
            $params[] = $user_id; $types.="i";
            $sql = "UPDATE documents SET ".implode(",",$sets)." WHERE id=? AND user_id=?";
            $st = $conn->prepare($sql);
            $st->bind_param($types,...$params);
            if ($st->execute()) {
                $success="Document actualizat.";
                
                $upNotif = $conn->prepare("DELETE FROM notifications WHERE document_id=? AND user_id=?");
                $upNotif->bind_param("ii", $doc_id, $user_id);
                $upNotif->execute();
                $upNotif->close();
                if ($expires_at) {
                    $noteText = "Documentul '{$type_new}' expiră la {$expires_at}.";
                    $notif = $conn->prepare("INSERT INTO notifications (user_id, vehicle_id, type, trigger_date, note, source, document_id) VALUES (?, ?, 'date', ?, ?, 'document', ?)");
                    $notif->bind_param('iissi', $user_id, $vehicle_n, $expires_at, $noteText, $doc_id);
                    $notif->execute();
                    $notif->close();
                }

            } else {
                $error="Eroare la actualizare: ".$st->error;
            }
            $st->close();
        }
    }
}


// 3) Fetch documents
$whereTab    = $tab === 'vehicle' ? 'AND d.vehicle_id IS NOT NULL' : 'AND d.vehicle_id IS NULL';
$whereFilter = ($tab === 'vehicle' && $filterVehicle !== null) ? 'AND d.vehicle_id=?' : '';
$sql = "
  SELECT d.id,d.file_path,d.type,d.note,d.expires_at,d.uploaded_at,
         d.vehicle_id,v.brand,v.model,v.year
    FROM documents d
    LEFT JOIN vehicles v ON d.vehicle_id=v.id
   WHERE d.user_id=? {$whereTab} {$whereFilter}
   ORDER BY d.uploaded_at DESC";
$st = $conn->prepare($sql);
if ($whereFilter) {
    $st->bind_param("ii", $user_id, $filterVehicle);
} else {
    $st->bind_param("i", $user_id);
}
$st->execute();
$docs = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

// 4) Fetch tax documents
$taxDocs = [];
if ($tab === 'vehicle') {
    $st = $conn->prepare(
        "SELECT t.id,t.photo_path AS file_path,
                t.tax_type AS type,t.notes AS note,
                t.due_date AS expires_at,t.created_at AS uploaded_at,
                t.vehicle_id,v.brand,v.model,v.year
           FROM taxes t
           JOIN vehicles v ON t.vehicle_id=v.id
          WHERE t.user_id=? AND t.add_to_documents=1 AND t.photo_path IS NOT NULL
          ORDER BY t.created_at DESC"
    );
    $st->bind_param("i", $user_id);
    $st->execute();
    $taxDocs = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
}

// 5) Group and prepare JS data
$all = array_merge($docs, $taxDocs);
usort($all, fn($a,$b) => strtotime($b['uploaded_at']) - strtotime($a['uploaded_at']));
$groups = [];
foreach ($all as $d) {
    $vehKey = $d['vehicle_id'] ? "{$d['vehicle_id']}" : '0';
    $key    = "{$d['type']}|{$d['note']}|{$d['expires_at']}|{$vehKey}";
    if (!isset($groups[$key])) {
        $groups[$key] = ['meta' => $d, 'records' => []];
    }
    $groups[$key]['records'][] = $d;
}
$jsData = [];
foreach ($groups as $k => $g) {
    $gid = substr(md5($k), 0, 8);
    foreach ($g['records'] as $r) {
        $jsData[$gid][] = [
            'id'         => $r['id'],
            'type'       => $r['type'],
            'note'       => $r['note'],
            'expires_at' => $r['expires_at'],
            'vehicle_id' => $r['vehicle_id'],
            'file_path'  => $r['file_path']
        ];
    }
}

include '../includes/header.php';
?>
<style>
.doc-card .carousel, .doc-card .carousel-inner, .doc-card .carousel-item { height: 200px; }
.doc-card img { width: 100%; height: 100%; object-fit: contain; object-position: center; }
#galleryModal .modal-body { text-align: center; padding: 1rem; }
#galleryModal .carousel-inner, #galleryModal .carousel-item { height: auto !important; max-height: 80vh; }
#galleryModal .carousel-item img, #galleryModal .carousel-item iframe { display: inline-block; width: 100%; height: 80vh; max-width: 100%; max-height: 80vh; object-fit: contain !important; margin: 0 auto; border: none; }
</style>

<div class="container-fluid">
  <ul class="nav nav-tabs mb-3">
    <li class="nav-item">
      <a class="nav-link <?= $tab==='vehicle'?'active':''?>" href="?tab=vehicle">Doc. Mașină</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $tab==='personal'?'active':''?>" href="?tab=personal">Doc. Personale</a>
    </li>
  </ul>

  <?php if($tab==='vehicle'): ?>
  <form method="get" class="form-inline mb-3">
    <input type="hidden" name="tab" value="vehicle">
    <label class="mr-2">Vehicul:</label>
    <select name="filter_vehicle" class="form-control mr-2">
      <option value="">Toate</option>
      <?php foreach($userVehicles as $v): ?>
        <option value="<?=$v['id']?>" <?= $filterVehicle===$v['id']?'selected':''?>>
          <?=htmlspecialchars("{$v['brand']} {$v['model']} ({$v['year']})")?>
        </option>
      <?php endforeach;?>
    </select>
    <button class="btn btn-secondary">Aplică</button>
  </form>
  <?php endif; ?>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2><?= $tab==='vehicle'?'Documente Mașină':'Documente Personale' ?></h2>
    <button class="btn btn-primary" data-toggle="modal" data-target="#addDocModal">
      <i class="fas fa-upload"></i> Încarcă Document
    </button>
  </div>

  <div class="row">
    <?php if (empty($groups)): ?>
        <div class="col-12"><p class="text-center text-muted">Nu există documente încărcate.</p></div>
    <?php endif; ?>
    <?php foreach($groups as $k=>$g):
      $meta = $g['meta'];
      $recs = $g['records'];
      $gid  = substr(md5($k),0,8);
    ?>
    <div class="col-6 col-sm-4 col-md-3 mb-4">
      <div class="card doc-card" data-gid="<?=$gid?>">
        <div class="card-body p-0">
          <div id="crs_<?=$gid?>" class="carousel slide" data-ride="carousel">
            <div class="carousel-inner">
              <?php foreach($recs as $i=>$r):
                $ext = strtolower(pathinfo($r['file_path'], PATHINFO_EXTENSION));
                $url = "/Licenta/pages/download.php?id={$r['id']}";
              ?>
              <div class="carousel-item <?= $i===0?'active':''?>" data-rid="<?=$r['id']?>">
                <?php if(in_array($ext,['jpg','jpeg','png'])): ?>
                  <img src="<?=$url?>" class="d-block w-100">
                <?php else: ?>
                  <div class="d-flex align-items-center justify-content-center bg-light" style="height:200px">
                    <i class="fas fa-file-alt fa-3x text-secondary"></i>
                  </div>
                <?php endif;?>
              </div>
              <?php endforeach;?>
            </div>
            <?php if(count($recs)>1): ?>
              <a class="carousel-control-prev" href="#crs_<?=$gid?>" data-slide="prev"><span class="carousel-control-prev-icon"></span></a>
              <a class="carousel-control-next" href="#crs_<?=$gid?>" data-slide="next"><span class="carousel-control-next-icon"></span></a>
            <?php endif;?>
          </div>
        </div>
        <div class="card-footer">
            <div>
                <strong><?=htmlspecialchars($meta['type'])?></strong><br>
                <?php if (!empty($meta['brand'])): ?>
                    <small class="text-muted" style="font-size: 0.8em;"><i class="fas fa-car-side fa-fw"></i> <?=htmlspecialchars("{$meta['brand']} {$meta['model']} ({$meta['year']})")?></small><br>
                <?php endif; ?>
                <small><?=htmlspecialchars($meta['note']?:'—')?></small><br>
                <small>Expiră: <?=$meta['expires_at']? date('d.m.Y', strtotime($meta['expires_at'])) : '—'?></small>
            </div>
            <div class="d-flex justify-content-end mt-2">
                <button class="btn btn-sm btn-info mr-2 view-group" data-gid="<?=$gid?>" title="Vizualizează">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn btn-sm btn-secondary mr-2 edit-group" data-gid="<?=$gid?>" title="Editează grupul">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-danger delete-group" data-gid="<?=$gid?>" title="Șterge grupul">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
      </div>
    </div>
    <?php endforeach;?>
  </div>
</div>

<form id="delGroupForm" method="post" style="display:none">
  <input type="hidden" name="form_type" value="del_group">
  <input type="hidden" name="doc_ids" id="delGroupDocIds">
</form>

<form id="delForm" method="post" style="display:none">
  <input type="hidden" name="form_type" value="del_doc">
  <input type="hidden" name="doc_id" id="delDocId">
</form>

<div class="modal fade" id="addDocModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" enctype="multipart/form-data" class="modal-content">
      <input type="hidden" name="form_type" value="add_doc">
      <div class="modal-header"><h5 class="modal-title">Încarcă Document</h5><button class="close" data-dismiss="modal">&times;</button></div>
      <div class="modal-body">
        <?php if($tab==='vehicle'): ?>
        <div class="form-group">
          <label>Vehicul</label>
          <select name="vehicle_id" class="form-control">
            <option value="">— fără —</option>
            <?php foreach($userVehicles as $v): ?>
              <option value="<?=$v['id']?>"><?=htmlspecialchars("{$v['brand']} {$v['model']} ({$v['year']})")?></option>
            <?php endforeach;?>
          </select>
        </div>
        <?php endif;?>
        <div class="form-group">
          <label>Tip</label>
          <select name="type" class="form-control" required>
            <option value="">— Alege —</option>
            <?php if($tab==='vehicle'): ?>
              <option>ITP</option><option>RCA</option><option>CASCO</option><option>RAR</option><option>Rovinietă</option><option>Taxă poluare</option><option>Taxă drum</option><option>Parcare</option><option>Carte service</option><option>Manual utilizare</option><option>Garanție</option><option>Altele</option>
            <?php else: ?>
              <option>Permis</option><option>CI</option><option>Pașaport</option><option>Cazier</option><option>Card medical</option><option>Altele</option>
            <?php endif;?>
          </select>
        </div>
        <div class="form-group"><label>Note</label><textarea name="note" class="form-control"></textarea></div>
        <div class="form-group"><label>Fișiere</label><input type="file" name="files[]" class="form-control-file" multiple required></div>
        <div class="form-group"><label>Expiră la</label><input type="date" name="expires_at" class="form-control"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Închide</button><button class="btn btn-primary">Încarcă</button></div>
    </form>
  </div>
</div>

<div class="modal fade" id="galleryModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Vizualizare document</h5><button class="close" data-dismiss="modal">&times;</button></div>
      <div class="modal-body p-0">
        <div id="galleryCarousel" class="carousel slide" data-ride="carousel">
          <div class="carousel-inner" id="galleryInner"></div>
          <a class="carousel-control-prev" href="#galleryCarousel" data-slide="prev"><span class="carousel-control-prev-icon"></span></a>
          <a class="carousel-control-next" href="#galleryCarousel" data-slide="next"><span class="carousel-control-next-icon"></span></a>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="editDocModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="editDocForm" method="post" enctype="multipart/form-data" class="modal-content">
      <input type="hidden" name="form_type" value="edit_doc">
      <input type="hidden" name="doc_id" id="editDocId">
      <div class="modal-header"><h5 class="modal-title">Editează Document</h5><button class="close" data-dismiss="modal">&times;</button></div>
      <div class="modal-body">
        <?php if($tab==='vehicle'): ?>
        <div class="form-group">
          <label>Vehicul</label>
          <select name="vehicle_id" id="editVehicleSelect" class="form-control">
            <option value="">— fără —</option>
            <?php foreach($userVehicles as $v): ?>
              <option value="<?=$v['id']?>"><?=htmlspecialchars("{$v['brand']} {$v['model']} ({$v['year']})")?></option>
            <?php endforeach;?>
          </select>
        </div>
        <?php endif;?>
        <div class="form-group">
          <label>Tip</label>
          <select name="type" id="editTypeSelect" class="form-control" required>
            <option value="">— Alege —</option>
            <?php if($tab==='vehicle'): ?>
              <option>ITP</option><option>RCA</option><option>CASCO</option><option>RAR</option><option>Rovinietă</option><option>Taxă poluare</option><option>Taxă drum</option><option>Parcare</option><option>Carte service</option><option>Manual utilizare</option><option>Garanție</option><option>Altele</option>
            <?php else: ?>
              <option>Permis</option><option>CI</option><option>Pașaport</option><option>Cazier</option><option>Card medical</option><option>Altele</option>
            <?php endif;?>
          </select>
        </div>
        <div class="form-group"><label>Înlocuiește fișier (opțional)</label><input type="file" name="new_file" class="form-control-file"><small class="form-text text-muted">Atenție: Aceasta va înlocui doar fișierul pentru acest element specific, nu pentru întregul grup.</small></div>
        <div class="form-group"><label>Note</label><textarea name="note" id="editNote" class="form-control"></textarea></div>
        <div class="form-group"><label>Expiră la</label><input type="date" name="expires_at" id="editExpires" class="form-control"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Închide</button><button class="btn btn-primary">Salvează</button></div>
    </form>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const groupData = <?= json_encode($jsData) ?>;

  // Listener pentru "Vizualizează"
  document.querySelectorAll('.view-group').forEach(btn => {
    btn.addEventListener('click', () => {
      const gid = btn.dataset.gid;
      const inner = document.getElementById('galleryInner');
      inner.innerHTML = '';
      groupData[gid].forEach((rec, i) => {
        const div = document.createElement('div');
        div.className = 'carousel-item' + (i === 0 ? ' active' : '');
        
        const url = `/Licenta/pages/download.php?id=${rec.id}`;
        const ext = rec.file_path ? rec.file_path.split('.').pop().toLowerCase() : '';

        if (['jpg', 'jpeg', 'png'].includes(ext)) {
          div.innerHTML = `<img src="${url}" alt="Document imagine">`;
        } else if (ext === 'pdf') {
          div.innerHTML = `<iframe src="${url}" title="Document PDF"></iframe>`;
        } else {
          div.innerHTML = `<div class="d-flex align-items-center justify-content-center bg-light" style="height:80vh;"><a href="${url}" class="btn btn-primary" target="_blank"><i class="fas fa-download"></i> Descarcă fișier (${ext})</a></div>`;
        }
        inner.appendChild(div);
      });
      $('#galleryModal').modal('show');
    });
  });

  // Listener pentru "Șterge Grup"
  document.querySelectorAll('.delete-group').forEach(btn => {
    btn.addEventListener('click', () => {
      if (!confirm('Ești sigur că vrei să ștergi acest grup de documente? Toate fișierele asociate vor fi eliminate definitiv.')) {
        return;
      }
      const gid = btn.dataset.gid;
      const ids = groupData[gid].map(rec => rec.id);
      
      document.getElementById('delGroupDocIds').value = ids.join(',');
      document.getElementById('delGroupForm').submit();
    });
  });

  // Listener pentru "Editează Grup"
  document.querySelectorAll('.edit-group').forEach(btn => {
    btn.addEventListener('click', () => {
      const gid = btn.dataset.gid;
      const firstRecord = groupData[gid][0]; 
      
      document.getElementById('editDocId').value = firstRecord.id; 
      document.getElementById('editTypeSelect').value = firstRecord.type;
      document.getElementById('editNote').value = firstRecord.note;
      
      const expiresDate = firstRecord.expires_at ? firstRecord.expires_at.split(' ')[0] : '';
      document.getElementById('editExpires').value = expiresDate;

      const vehicleSelect = document.getElementById('editVehicleSelect');
      if (vehicleSelect) {
        vehicleSelect.value = firstRecord.vehicle_id || '';
      }
      
      $('#editDocModal').modal('show');
    });
  });
});
</script>