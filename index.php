<?php
require 'config.php';
$cart = &$_SESSION['cart']; $cart = $cart ?? [];
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'add' && $id) { $cart[$id] = min(20, ($cart[$id] ?? 0) + 1); }
    if ($action === 'minus' && isset($cart[$id])) { if (--$cart[$id] <= 0) unset($cart[$id]); }
    if ($action === 'order' && $cart) {
        $nom = trim($_POST['nom'] ?? ''); $tel = trim($_POST['tel'] ?? '');
        $adr = trim($_POST['adresse'] ?? ''); $note = trim($_POST['note'] ?? '');
        if ($nom === '' || $tel === '' || $adr === '') {
            $erreur = 'Renseignez votre nom, votre téléphone et votre adresse de livraison.';
        } else {
            $in = implode(',', array_fill(0, count($cart), '?'));
            $st = db()->prepare("SELECT * FROM plats WHERE disponible = 1 AND id IN ($in)");
            $st->execute(array_keys($cart));
            $dispo = $st->fetchAll();
            if (!$dispo) { $erreur = 'Votre panier ne contient plus de plat disponible.'; $cart = []; }
            else {
                $pdo = db(); $pdo->beginTransaction();
                $total = 0; foreach ($dispo as $p) $total += $p['prix'] * $cart[$p['id']];
                $pdo->prepare('INSERT INTO commandes (client_nom, telephone, adresse, note, total) VALUES (?,?,?,?,?)')
                    ->execute([$nom, $tel, $adr, $note, $total]);
                $cid = (int)$pdo->lastInsertId();
                $ins = $pdo->prepare('INSERT INTO lignes (commande_id, plat_id, nom, prix, quantite) VALUES (?,?,?,?,?)');
                foreach ($dispo as $p) $ins->execute([$cid, $p['id'], $p['nom'], $p['prix'], $cart[$p['id']]]);
                $pdo->commit();
                $cart = [];
                header("Location: index.php?ok=$cid"); exit;
            }
        }
    } else { header('Location: index.php'); exit; }
}

$plats = db()->query('SELECT * FROM plats WHERE disponible = 1 ORDER BY nom')->fetchAll();
$byId = [];
foreach ($plats as $p) { $byId[$p['id']] = $p; }
$total = 0;
foreach ($cart as $id => $q) { if (isset($byId[$id])) $total += $byId[$id]['prix'] * $q; else unset($cart[$id]); }
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Chez Maman — commandez en ligne</title><link rel="stylesheet" href="style.css"></head>
<body>
<header><h1>Chez Maman</h1><p>Cuisine béninoise, livrée chaude.</p></header>
<?php if (isset($_GET['ok'])): ?>
  <p class="ok">Commande n°<?= (int)$_GET['ok'] ?> enregistrée. Nous vous appelons pour confirmer la livraison.</p>
<?php endif; ?>
<main>
<?php $groupes = ['repas' => 'Nos plats', 'boisson' => 'Nos jus']; ?>
<section class="menu">
<?php foreach ($groupes as $cat => $titre): ?>
  <h2><?= $titre ?></h2>
    <?php foreach ($plats as $p): if (($p['categorie'] ?? 'repas') !== $cat) continue; ?>
    <div class="plat">
      <img src="images/<?= h(($p['image'] ?? '') ?: 'defaut.svg') ?>" alt="<?= h($p['nom']) ?>" loading="lazy">
      <div><strong><?= h($p['nom']) ?></strong><span><?= h($p['description']) ?></span></div>
      <b><?= fcfa($p['prix']) ?></b>
    </div>
  <?php endforeach; ?>
<?php endforeach; ?>
</section>
</main>
<div class="barre-boutons">
  <a href="commander.php" class="btn-commande">Passez vos commandes</a>
  <a href="suivi.php" class="btn-commande btn-suivi">Suivre ma commande</a>
</div>
</body>
</html>