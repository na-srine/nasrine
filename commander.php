<?php
require 'config.php';
$erreur = '';
$options = ['', 'Sans piment', 'Piment à part', 'Pas d’oignons'];
$qte = [];

$plats = db()->query('SELECT * FROM plats WHERE disponible = 1 ORDER BY nom')->fetchAll();
$byId = [];
foreach ($plats as $p) { $byId[$p['id']] = $p; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $nom  = trim($_POST['nom'] ?? '');
    $tel  = trim($_POST['tel'] ?? '');
    $adr  = trim($_POST['adresse'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $note = in_array($note, $options, true) ? $note : '';
    foreach (($_POST['qte'] ?? []) as $id => $q) {
        $id = (int)$id; $q = max(0, min(20, (int)$q));
        if ($q > 0 && isset($byId[$id])) $qte[$id] = $q;
    }
    if (!$qte) {
        $erreur = 'Choisissez au moins un plat ou un jus.';
    } elseif ($nom === '' || $tel === '' || $adr === '') {
        $erreur = 'Renseignez votre nom, votre téléphone et votre adresse de livraison.';
    } else {
        $total = 0;
        foreach ($qte as $id => $q) $total += $byId[$id]['prix'] * $q;
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO commandes (client_nom, telephone, adresse, note, total) VALUES (?,?,?,?,?)')
                ->execute([$nom, $tel, $adr, $note, $total]);
            $cid = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO commandes_plats (commande_id, plat_id, nom, prix, quantite) VALUES (?,?,?,?,?)');
            foreach ($qte as $id => $q) {
                $p = $byId[$id];
                $ins->execute([$cid, $p['id'], $p['nom'], $p['prix'], $q]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        $_SESSION['cart'] = [];
        $_SESSION['suivi'] = $cid;
header('Location: suivi.php?ok=1'); exit;
    }
} else {
    foreach (($_SESSION['cart'] ?? []) as $id => $q) {
        if (isset($byId[$id])) $qte[$id] = (int)$q;
    }
}

$total = 0;
foreach ($qte as $id => $q) $total += $byId[$id]['prix'] * $q;
$groupes = ['repas' => 'Nos plats', 'boisson' => 'Nos jus'];
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Passez vos commandes — Chez Maman</title><link rel="stylesheet" href="style.css"></head>
<body>
<header><h1>Chez Maman</h1><p>Cuisine béninoise, livrée chaude.</p></header>
<main class="commande">
  <p><a href="index.php">← Retour au menu</a></p>
  <h2>Passez vos commandes</h2>
  <?php if ($erreur): ?><p class="err"><?= h($erreur) ?></p><?php endif; ?>

  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">

    <?php foreach ($groupes as $cat => $titre): ?>
      <h3><?= $titre ?></h3>
      <?php foreach ($plats as $p): if (($p['categorie'] ?? 'repas') !== $cat) continue; ?>
        <div class="plat">
          <img src="images/<?= h(($p['image'] ?? '') ?: 'defaut.svg') ?>" alt="<?= h($p['nom']) ?>" loading="lazy">
          <div><strong><?= h($p['nom']) ?></strong><span><?= h($p['description']) ?></span></div>
          <b><?= fcfa($p['prix']) ?></b>
          <label class="qte">Qté
            <input type="number" name="qte[<?= $p['id'] ?>]" min="0" max="20"
                   value="<?= $qte[$p['id']] ?? 0 ?>" data-prix="<?= (int)$p['prix'] ?>">
          </label>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>

    <p class="total">Total <b id="total"><?= fcfa($total) ?></b></p>

    <div class="envoi">
      <label>Nom<input name="nom" required value="<?= h($_POST['nom'] ?? '') ?>"></label>
      <label>Téléphone<input name="tel" type="tel" required value="<?= h($_POST['tel'] ?? '') ?>"></label>
      <label>Adresse de livraison<input name="adresse" required value="<?= h($_POST['adresse'] ?? '') ?>"></label>
      <label>Précisions 
  <select name="note">
    <?php foreach ($options as $o): ?>
      <option value="<?= h($o) ?>" <?= ($_POST['note'] ?? '') === $o ? 'selected' : '' ?>>
        <?= $o === '' ? 'Aucune précision' : h($o) ?>
      </option>
    <?php endforeach; ?>
  </select>
</label>
      <button class="primaire">Commander et paiement à la livraison</button>
    </div>
  </form>
</main>

<script>
const champs = document.querySelectorAll('input[data-prix]');
const totalEl = document.getElementById('total');
function maj() {
  let t = 0;
  champs.forEach(c => t += (+c.dataset.prix) * Math.max(0, Math.min(20, +c.value || 0)));
  totalEl.textContent = t.toLocaleString('fr-FR') + ' FCFA';
}
champs.forEach(c => c.addEventListener('input', maj));
</script>
</body></html>